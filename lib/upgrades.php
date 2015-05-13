<?php

function granular_access_20150513() {
	$version = elgg_get_plugin_setting('version', 'granular_access');
	if ($version >= GranularAccess\PLUGIN_VERSION) {
		return; // no need to run this upgrade
	}
	
	// need to make sure people who have joined groups are in the right acl
	$options = array(
		'type' => 'object',
		'subtype' => 'granular_access',
		'limit' => false
	);
	
	$batch = new ElggBatch('elgg_get_entities', $options);
	
	foreach ($batch as $e) {
		error_log('repopulating ' . $e->guid);
		\GranularAccess\repopulate_acl($e);
	}
	
	elgg_set_plugin_setting('version', 20150513, 'granular_access');
}