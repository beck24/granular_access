<?php

namespace GranularAccess;

$classes = elgg_extract('class', $vars);
if (!is_array($classes) || !in_array('elgg-input-access', $classes)) {
    return;
}

// check to see if the current value is a granular access
$acl = get_access_collection($vars['value']);

$granular_access = false;
$hidden = ' hidden';
$set_custom = false;

if ($acl) {
	$ga = elgg_get_entities_from_metadata(array(
		'type' => 'object',
		'subtype' => 'granular_access',
		'metadata_name_value_pairs' => array(
			'name' => 'acl_id',
			'value' => $acl->id
		)
	));

	if ($ga) {
		$granular_access = $ga[0];
	}
}

// determine whether we display this filled out by default
// only do this if $granluar_access is valid
// AND there's no existing matching option in the dropdown
if ($granular_access) {
	if (is_array($vars['options_values'])) {
		$options_values = $vars['options_values'];
	} else {
		$options_values = get_write_access_array();
	}

	if (array_search($vars['value'], $options_values) === false) {
		$set_custom = true;
	}
}

$name = $vars['name'] ? $vars['name'] : 'access_id';
preg_match("/^(.+)\[(.+)\]$/", $name, $field);
if( count($field) > 2 ) {
    $name = $field[1].GRANULAR_ACCESS_SEPARATOR.$field[2];
}
    
echo elgg_view('input/hidden', array('name' => 'granular_access_names[]', 'value' => $name));

if ($set_custom && preg_match('/^granular_access:.{32}$/',$acl->name)) {
	// this is a granular_access value, so we should show the form by default
	$hidden = '';
}

$default_callback = __NAMESPACE__ . '\\tokeninput_search';
$callback = elgg_trigger_plugin_hook('granular_access', 'search_callback', $vars, $default_callback);

?>
<div class="granular-access-wrapper<?php echo $hidden; ?> pam" data-name="<?php echo $name; ?>">
	<label><?php echo elgg_echo('granular_access:custom:access:label'); ?></label>
	<?php
	echo elgg_view('input/tokeninput', array(
		'name' => 'ga_build_' . $name,
		'value' => $granular_access ? (array) $granular_access->access_list : array(),
		'multiple' => true,
		'callback' => $callback
	));

	echo elgg_view('output/longtext', array(
		'value' => elgg_echo('granular_access:custom:access:help'),
		'class' => 'elgg-subtext'
	));
	?>
</div>
