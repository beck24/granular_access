<?php

namespace GranularAccess;


function acl_write_options($h, $t, $r, $p) {
	$user = get_user($p['user_id']);
	
	if (can_use_granular($user)) {
		$r['granular'] = elgg_echo('granular_access:custom');
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
		$original = get_input($name);
		
		if ($original != 'granular') {
			continue;
		}
		
		if (!$input && is_numeric($original)) {
			// leave it alone
			continue;
		}
		elseif (!$input && $original == 'granular') {
			set_input($name, ACCESS_PRIVATE);
			continue;
		}

		// lets build a collection
		$access_id = get_access_for_guids($input);
		
		set_input($name, $access_id);
	}
	
	set_input('granular_access_names', null);
	
	return $r;
}