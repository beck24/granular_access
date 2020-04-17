<?php

namespace GranularAccess;

use ElggObject;
use ElggBatch;

function can_use_granular($user) {
	$result = true;
	
	if (!elgg_instanceof($user, 'user')) {
		$result = false;
	}
	
	// some logic yet to be determined
	// allow other plugins to weigh in
	return elgg_trigger_plugin_hook('granular_access', 'can_use', array('user' => $user), $result);
}

function tokeninput_search($query, $options = array()) {

	$query = sanitize_string($query);

	// replace mysql vars with escaped strings
	$q = str_replace(array('_', '%'), array('\_', '\%'), $query);

	$options['types'] = array('user', 'group');
	$options['search_name_value_pairs'] = [
		[
			'name' => ['name', 'username'],
			'value' => '%' . $q . '%',
			'operand' => 'LIKE',
			'case_sensitive' => false
		]
	];

	return elgg_get_entities($options);
}

function get_access_for_guids($guids) {
	if (!is_array($guids)) {
		$guids = (array) $guids;
	}

	$guids = array_unique($guids);

	if (!$guids || (count($guids) == 1 && !$guids[0])) {
		return ACCESS_PRIVATE;
	}

	// check for an existing matching ACL object
	$token = get_token_from_guids($guids);

	// check for existing acl with this token
	$options = array(
		'type' => 'object',
		'subtype' => 'granular_access',
		'metadata_name_value_pairs' => array(
			'name' => 'token',
			'value' => $token
		)
	);

	$granular_access = elgg_get_entities_from_metadata($options);
	if ($granular_access) {
		return $granular_access[0]->acl_id;
	}

	// we have no matching acls
	return build_acl_from_guids($guids);
}

function get_token_from_guids($guids) {
	$guids = array_unique($guids);
	sort($guids);
	$string = implode(',', $guids);
	return md5($string);
}

function build_acl_from_guids($guids) {
	$site = elgg_get_site_entity();
	$token = get_token_from_guids($guids);

	$ia = elgg_set_ignore_access(true);
	$granular_access = new ElggObject();
	$granular_access->subtype = 'granular_access';
	$granular_access->access_id = ACCESS_PUBLIC;
	$granular_access->owner_guid = $site->guid;
	$granular_access->container_guid = $site->guid;
	$granular_access->token = $token;
	$granular_access->access_list = $guids;

	$guid = $granular_access->save();

	if (!$guid) {
		elgg_set_ignore_access($ia);
		return false;
	}

	// check, if this is a single group, lets use the acl from that
	if (count($guids) == 1) {
		$entity = get_entity($guids[0]);
		if (elgg_instanceof($entity, 'group') && $entity->group_acl) {
			$granular_access->acl_id = $entity->group_acl;
			$granular_access->single_group = 1; // flag for use later to tell that this is using groups default acl
			// no need to populate
			elgg_set_ignore_access($ia);
			return $entity->group_acl;
		}
	}

	$id = create_access_collection('granular_access:' . $token, $site->guid, 'granular_access');
	$granular_access->acl_id = $id;

	elgg_set_ignore_access($ia);

	// actually populating the acl can take a long time, so we save that for vroom
	// make it happen on the shutdown event
	// add our guid to a list to populate
	register_new_granular_access($guid);

	if (!$GLOBALS['shutdown_flag']) {
		// unregister first so we don't end up with multiple firings of the event
		elgg_unregister_event_handler('shutdown', 'system', __NAMESPACE__ . '\\populate_acls');
		elgg_register_event_handler('shutdown', 'system', __NAMESPACE__ . '\\populate_acls');
	}
	else {
		populate_acls();
	}
	
	return $id;
}

function register_new_granular_access($guid) {
	$guids = elgg_get_config('new_granular_access');

	if (!is_array($guids)) {
		$guids = array();
	}

	$guids[] = $guid;

	elgg_set_config('new_granular_access', array_unique($guids));
}

// add all group members to an acl
// use ElggBatch for OOM management
function add_group_to_access_collection($group, $acl_id) {
	$options = array(
		'type' => 'user',
		'limit' => false,
		'relationship' => 'member',
		'relationship_guid' => $group->guid,
		'inverse_relationship' => true,
		'callback' => false, //make queries faster as we don't need the user object
	);

	$batch = new ElggBatch('elgg_get_entities_from_relationship', $options);

	foreach ($batch as $u) {
		add_user_to_access_collection($u->guid, $acl_id);
	}
}

function remember_join_group($group, $user) {
	$joins = elgg_get_config('granular_access_joins');
	if (!is_array($joins)) {
		$joins = array();
	}

	// use guids so we're not storing a ton of entities in memory
	// in case big scripts make changes, want to limit OOM issues
	$joins[] = array('group' => $group->guid, 'user' => $user->guid);

	elgg_set_config('granular_access_joins', $joins);
}

function remember_leave_group($group, $user) {
	$leaves = elgg_get_config('granular_access_leaves');
	if (!is_array($leaves)) {
		$leaves = array();
	}

	// use guids so we're not storing a ton of entities in memory
	// in case big scripts make changes, want to limit OOM issues
	$leaves[] = array('group' => $group->guid, 'user' => $user->guid);

	elgg_set_config('granular_access_leaves', $leaves);
}

function remember_delete_group($group) {
	$deletions = elgg_get_config('granular_access_deletions');
	if (!is_array($deletions)) {
		$deletions = array();
	}

	// use guids so we're not storing a ton of entities in memory
	// in case big scripts make changes, want to limit OOM issues
	$deletions[$group->guid] = 1; // also use key to keep unique

	elgg_set_config('granular_access_deletions', $deletions);
}

function repopulate_acl($granular_access) {
	$ia = elgg_set_ignore_access(true);
	$dbprefix = elgg_get_config('dbprefix');
	
	// empty the acl first
	$q = "DELETE FROM {$dbprefix}access_collection_membership
		WHERE access_collection_id = {$granular_access->acl_id}";
	delete_data($q);
	
	//loop through all of the guids this acl encompasses
	$list = (array) $granular_access->access_list;

	foreach ($list as $l) {
		$e = get_entity($l);
		if (elgg_instanceof($e, 'user')) {
			add_user_to_access_collection($e->guid, $granular_access->acl_id);
			continue;
		}

		if (elgg_instanceof($e, 'group')) {
			add_group_to_access_collection($e, $granular_access->acl_id);
			continue;
		}
		
		//@TODO - anything to do here?
	}
	
	elgg_set_ignore_access($ia);
}


/**
 * when duplicate access is detected we'll transfer all content assigned to
 * one and assign it to the other
 * All content from $access1 will now be under $access2
 * 
 * @param type $access1
 * @param type $access2
 */
function merge_access($access1, $access2) {
	if (!elgg_instanceof($access1, 'object', 'granular_access') || !$access1->acl_id) {
		return false;
	}
	
	if (!elgg_instanceof($access2, 'object', 'granular_access') || !$access2->acl_id) {
		return false;
	}
	
	$dbprefix = elgg_get_config('dbprefix');
	$sql = "UPDATE {$dbprefix}entities SET access_id = {$access2->acl_id} WHERE access_id = {$access1->acl_id}";
	update_data($sql);
	
	$sql = "UPDATE {$dbprefix}metadata SET access_id = {$access2->acl_id} WHERE access_id = {$access1->acl_id}";
	update_data($sql);
	
	$sql = "UPDATE {$dbprefix}annotations SET access_id = {$access2->acl_id} WHERE access_id = {$access1->acl_id}";
	update_data($sql);
	
	return true;
}

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