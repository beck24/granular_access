<?php

namespace GranularAccess;

class Events {

    /**
     * @param $e string = 'join'
     * @param $t string = 'group'
     * @param $p array
     */
    public static function joinGroup($e, $t, $params) {
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


    public static function leaveGroup($e, $t, $params) {
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

    public static function deleteGroup($e, $t, $group) {
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
}