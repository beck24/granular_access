<?php

/**
 * Granular Access
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
 * @author Matt Beckett
 * @copyright Matt Beckett 2014
 */

//@TODO - cleanup unused acls

namespace GranularAccess;

const PLUGIN_ID = 'granular_access';
const PLUGIN_VERSION = 20150513;

require_once __DIR__ . '/lib/functions.php';
require_once __DIR__ . '/lib/hooks.php';
require_once __DIR__ . '/lib/events.php';

elgg_register_event_handler('init', 'system', __NAMESPACE__ . '\\init');

function init() {
	elgg_register_library('granular_access:upgrades', __DIR__ . '/lib/upgrades.php');
	elgg_extend_view('input/access', 'input/granular_access');
	elgg_extend_view('js/elgg', 'js/granular_access');
	
	//register our hooks
	// add a 'custom' option into the acl list
	elgg_register_plugin_hook_handler('access:collections:write', 'user', __NAMESPACE__ . '\\acl_write_options');
	elgg_register_plugin_hook_handler('action', 'all', __NAMESPACE__ . '\\action_submit', 2);
	elgg_register_plugin_hook_handler('cron', 'weekly', __NAMESPACE__ . '\\weekly_cron');
	
	// register these late in case some other event handler prevents joining/leaving
	elgg_register_event_handler('join', 'group', __NAMESPACE__ . '\\join_group', 1000);
	elgg_register_event_handler('leave', 'group', __NAMESPACE__ . '\\leave_group', 1000);
	elgg_register_event_handler('delete', 'group', __NAMESPACE__ . '\\delete_group', 1000);
	
	elgg_register_event_handler('upgrade', 'system', __NAMESPACE__ . '\\upgrades');
}