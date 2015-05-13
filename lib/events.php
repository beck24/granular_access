<?php

namespace GranularAccess;

use ElggBatch;

// called on the shutdown handler for use with vroom
function populate_acls() {
	$ia = elgg_set_ignore_access(true);
	$guids = elgg_get_config('new_granular_access');

	if (!is_array($guids)) {
		return true;
	}

	foreach ($guids as $guid) {
		$granular_access = get_entity($guid);
		if (!elgg_instanceof($granular_access, 'object', 'granular_access')) {
			continue;
		}
		
		repopulate_acl($granular_access);

	}
	elgg_set_config('new_granular_access', array());
	elgg_set_ignore_access($ia);
}

function join_group($e, $t, $params) {
	// save for the shutdown handler to process
	remember_join_group($params['group'], $params['user']);
	
	// is this a cron script?
	$uri = str_replace(elgg_get_site_url(), '', current_page_url());
	$is_cron = (strpos($uri, 'cron/') === 0);

	// unregister first so we don't end up with multiple firings of the event
	if (!$GLOBALS['shutdown_flag'] && !$is_cron) {
		elgg_unregister_event_handler('shutdown', 'system', __NAMESPACE__ . '\\process_group_joins');
		elgg_register_event_handler('shutdown', 'system', __NAMESPACE__ . '\\process_group_joins');
	}
	else {
		process_group_joins();
	}
}

function leave_group($e, $t, $params) {
	// save for the shutdown handler to process
	remember_leave_group($params['group'], $params['user']);
	
	// is this a cron script?
	$uri = str_replace(elgg_get_site_url(), '', current_page_url());
	$is_cron = (strpos($uri, 'cron/') === 0);

	if (!$GLOBALS['shutdown_flag'] && !$is_cron) {
		// unregister first so we don't end up with multiple firings of the event
		elgg_unregister_event_handler('shutdown', 'system', __NAMESPACE__ . '\\process_group_leaves');
		elgg_register_event_handler('shutdown', 'system', __NAMESPACE__ . '\\process_group_leaves');
	}
	else {
		process_group_leaves();
	}
}


function delete_group($e, $t, $group) {
	remember_delete_group($group);
	
	if (!$GLOBALS['shutdown_flag']) {
		// unregister first so we don't end up with multiple firings of the event
		elgg_unregister_event_handler('shutdown', 'system', __NAMESPACE__ . '\\process_group_deletion');
		elgg_register_event_handler('shutdown', 'system', __NAMESPACE__ . '\\process_group_deletion');
	}
	else {
		process_group_deletion();
	}
}

// called on shutdown handler
// performs logic based on people joining groups
function process_group_joins() {
	$joins = elgg_get_config('granular_access_joins');

	if (!is_array($joins)) {
		return true;
	}

	foreach ($joins as $params) {
		$options = array(
			'type' => 'object',
			'subtype' => 'granular_access',
			'metadata_name_value_pairs' => array(
				'name' => 'access_list',
				'value' => $params['group']
			),
			'limit' => false
		);

		// get granular access objects that pertain to this group
		$batch = new ElggBatch('elgg_get_entities_from_metadata', $options);

		foreach ($batch as $granular_access) {
			if ($granular_access->single_group) {
				// this uses the default group acl
				continue;
			}

			add_user_to_access_collection($params['user'], $granular_access->acl_id);
		}
	}
	
	elgg_set_config('granular_access_joins', array());
}

// called on shutdown handler
// performs logic based on people leaving groups
function process_group_leaves() {
	$leaves = elgg_get_config('granular_access_leaves');

	if (!is_array($leaves)) {
		return true;
	}

	foreach ($leaves as $params) {
		$options = array(
			'type' => 'object',
			'subtype' => 'granular_access',
			'metadata_name_value_pairs' => array(
				'name' => 'access_list',
				'value' => $params['group']
			),
			'limit' => false
		);

		// get granular access objects that pertain to this group
		$batch = new ElggBatch('elgg_get_entities_from_metadata', $options);

		foreach ($batch as $granular_access) {
			if ($granular_access->single_group) {
				// this uses the default group acl
				continue;
			}

			// here's where it gets tricky, we want to remove them if there's no other reason to keep them
			// that is they aren't explicitly mentioned, and they aren't in another group
			$guids = (array) $granular_access->access_list;
			if (in_array($params['user'], $guids)) {
				// they are explicitly listed, so do nothing, they stay in the acl
				continue;
			}

			// remove the guid of this group from the list, and count other groups where this user is a member
			unset($guids[array_search($params['group'], $guids)]);

			if ($guids) {

				$ia = elgg_set_ignore_access(true); // in case of hidden groups!
				$count = elgg_get_entities_from_relationship(array(
					'guids' => $guids,
					'type' => 'group',
					'relationship' => 'member',
					'relationship_guid' => $params['user'],
					'inverse_relationship' => false,
					'count' => true
				));
				elgg_set_ignore_access($ia);

				if ($count) {
					continue;
				}
			}

			remove_user_from_access_collection($params['user'], $granular_access->acl_id);
		}
	}
	
	elgg_set_config('granular_access_leaves', array());
}


/**
 * sort out acls when groups get deleted
 * @return boolean
 */
function process_group_deletion() {
	$deletions = elgg_get_config('granular_access_deletions');
	
	if (!is_array($deletions)) {
		return true;
	}
	
	$guids = array_keys($deletions);
	
	// note these guids don't exist anymore
	$options = array(
		'type' => 'object',
		'subtype' => 'granular_access',
		'metadata_name_value_pairs' => array(
			'name' => 'access_list',
			'value' => $guids
		),
		'limit' => false
	);
	
	$batch = new ElggBatch('elgg_get_entities_from_metadata', $options);
	
	$ia = elgg_set_ignore_access(true);
	foreach ($batch as $granular_access) {
		if ($granular_access->single_group) {
			$granular_access->delete();
			continue;
		}
		
		$access_list = (array) $granular_access->access_list;
		
		// remove any of the guids from the access_list
		$access_list = array_diff($access_list, $guids);
		
		if (!$access_list) {
			// the access list is empty no need for this access
			delete_access_collection($granular_access->acl_id);
			$granular_access->delete();
			continue;
		}
		
		// make sure this doesn't just become a duplicate
		$token = get_token_from_guids($access_list);
		$entities = elgg_get_entities_from_metadata(array(
			'type' => 'object',
			'subtype' => 'granular_access',
			'metadata_name_value_pairs' => array(
				'name' => 'token',
				'value' => $token
			),
			'wheres' => array("e.guid != {$granular_access->guid}")
		));
			
		if ($entities) {
			if (merge_access($granular_access, $entities[0])) {
				delete_access_collection($granular_access->acl_id);
				$granular_access->delete();
				continue;
			}
		}
		
		$granular_access->access_list = $access_list;
		
		repopulate_access_collection($granular_access);
	}
	
	elgg_set_config('granular_access_deletions', array());
	elgg_set_ignore_access($ia);
}


function upgrades() {
	if (!elgg_is_admin_logged_in()) {
		return;
	}
	elgg_load_library('granular_access:upgrades');
	run_function_once('granular_access_20150513');
}