<?php

namespace GranularAccess;

use Elgg\Includer;
use Elgg\PluginBootstrap;

class Bootstrap extends PluginBootstrap {

	/**
	 * Get plugin root
	 * @return string
	 */
	protected function getRoot() {
		return $this->plugin->getPath();
	}

	/**
	 * {@inheritdoc}
	 */
	public function load() {
        Includer::requireFileOnce($this->getRoot() . '/lib/functions.php');
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot() {

	}

	/**
	 * {@inheritdoc}
	 */
	public function init() {
        elgg_extend_view('input/access', 'input/granular_access');
    
        //register our hooks
        // add a 'custom' option into the acl list
        elgg_register_plugin_hook_handler('access:collections:write', 'user', [Hooks::class, 'aclWriteOptions']);
        elgg_register_plugin_hook_handler('action', 'all', [Hooks::class, 'actionSubmit'], 2);
        elgg_register_plugin_hook_handler('cron', 'weekly', [Hooks::class, 'weeklyCron']);
		elgg_register_plugin_hook_handler('cron', 'hourly', [Hooks::class, 'hourlyCron']);
		elgg_register_plugin_hook_handler('access_collection:name', 'access_collection', [Hooks::class, 'getAclName']);
    
        // register these late in case some other event handler prevents joining/leaving
        elgg_register_event_handler('join', 'group', [Events::class, 'joinGroup'], 1000);
        elgg_register_event_handler('leave', 'group', [Events::class, 'leaveGroup'], 1000);
		elgg_register_event_handler('delete', 'group', [Events::class, 'deleteGroup'], 1000);
	}

	/**
	 * {@inheritdoc}
	 */
	public function ready() {

	}

	/**
	 * {@inheritdoc}
	 */
	public function shutdown() {

	}

	/**
	 * {@inheritdoc}
	 */
	public function activate() {

	}

	/**
	 * {@inheritdoc}
	 */
	public function deactivate() {

	}

	/**
	 * {@inheritdoc}
	 */
	public function upgrade() {

	}

}