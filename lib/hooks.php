<?php

namespace GranularAccess;
use ElggBatch;

function acl_write_options($h, $t, $r, $p) {
	$user = get_user($p['user_id']);
	
	if (can_use_granular($user)) {
		$r[GRANULAR_ACCESS_OPTION] = elgg_echo('granular_access:custom');
	}
	
	return $r;
}

// an action has been submitted, check and modify access inputs
function action_submit($h, $t, $r, $p) {
	$granular_inputs = get_input('granular_access_names');
	
	if (!is_array($granular_inputs)) {
		return $r;
	}
	
	foreach ($granular_inputs as $name) {
		$input = get_input('ga_build_' . $name);
                
                $regexp = "/^(.+)".GRANULAR_ACCESS_SEPARATOR."(.+)$/";;
                preg_match($regexp, $name, $field);
                if (count($field) > 2) {
                        $accesses = get_input("{$field[1]}");
                        $original = (int)$accesses[$field[2]];
                } else {
        		$original = (int)get_input($name);                    
                }
		
		if ($original != GRANULAR_ACCESS_OPTION) {
			continue;
		}
		
		if (!$input && ($original != GRANULAR_ACCESS_OPTION)) {
			// leave it alone
			continue;
		}
		elseif (!$input && $original == GRANULAR_ACCESS_OPTION) {
			set_input($name, ACCESS_PRIVATE);
			continue;
		}

		// lets build a collection
		$access_id = get_access_for_guids($input);
                if( count($field) > 2) {
                        $accesses[$field[2]] = $access_id;
                        set_input("{$field[1]}", $accesses);
                } else {
        		set_input($name, $access_id);                    
                }        
	}
	
	set_input('granular_access_names', null);
	
	return $r;
}


/**
 * @TODO - is there a way we can target them directly instead of iterating through all?
 * 
 * @param type $h
 * @param type $t
 * @param type $r
 * @param type $p
 * @return type
 */
function weekly_cron($h, $t, $r, $p) {
	$ia = elgg_set_ignore_access(true);
	// check for abandoned acls
	$dbprefix = elgg_get_config('dbprefix');
	
	// try to make this as light as possible
	$options = array(
		'type' => 'object',
		'subtype' => 'granular_access',
		'limit' => false
	);
	
	$batch = new ElggBatch('elgg_get_entities', $options);
	
	foreach ($batch as $b) {
		$sql = "SELECT COUNT(guid) as total FROM {$dbprefix}entities WHERE access_id = {$b->acl_id}";
		$result = get_data($sql);
		
		if ($result[0]->total) {
			continue;
		}
		
		$sql = "SELECT COUNT(id) as total FROM {$dbprefix}metadata WHERE access_id = {$b->acl_id}";
		$result = get_data($sql);
		if ($result[0]->total) {
			continue;
		}
		
		$sql = "SELECT COUNT(id) as total FROM {$dbprefix}annotations WHERE access_id = {$b->acl_id}";
		$result = get_data($sql);
		if ($result[0]->total) {
			continue;
		}
		
		// this appears to be orphaned
		delete_access_collection($b->acl_id);
		$b->delete();
	}
	
	elgg_set_ignore_access($ia);
	
	return $r;
}

/**
 * Pass info to javascript
 * 
 * @param type $h
 * @param type $t
 * @param type $r
 * @param type $p
 * @return type
 */
function config_site($hook, $type, $value, $params) {
        // this will be cached client-side
        $value[PLUGIN_ID]['GRANULAR_ACCESS_OPTION'] = GRANULAR_ACCESS_OPTION;
        $value[PLUGIN_ID]['GRANULAR_ACCESS_SEPARATOR'] = GRANULAR_ACCESS_SEPARATOR;
        
        return $value;
}