<?php

namespace GranularAccess;

class Hooks {
    public static function hourlyCron($h, $t, $r, $p) {
        if (!elgg_get_plugin_setting('activated', 'granular_access')) {
            return $r;
        }

        // need to make sure people who have joined groups are in the right acl
        $options = array(
            'type' => 'object',
            'subtype' => 'granular_access',
            'limit' => false
        );

        $batch = new ElggBatch('elgg_get_entities', $options);

        foreach ($batch as $e) {
            \GranularAccess\repopulate_acl($e);
        }

        elgg_unset_plugin_setting('activated', 'granular_access');
    }


    public static function weeklyCron($h, $t, $r, $p) {
        $ia = elgg_set_ignore_access(true);
        // check for abandoned acls
        $dbprefix = elgg_get_config('dbprefix');
        
        // try to make this as light as possible
        $options = array(
            'type' => 'object',
            'subtype' => 'granular_access',
            'limit' => false
        );
        
        $batch = new \ElggBatch('elgg_get_entities', $options);
        
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

    // an action has been submitted, check and modify access inputs
    public static function actionSubmit($h, $t, $r, $p) {
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

    public static function aclWriteOptions($h, $t, $r, $p) {
        $user = get_user($p['user_id']);
        
        if (can_use_granular($user)) {
            $r['granular'] = elgg_echo('granular_access:custom');
        }
        
        return $r;
    }


    public static function getAclName($h, $t, $r, $p) {
        if ($p['access_collection'] && $p['access_collection']->getSubtype() === 'granular_access') {
            return elgg_echo('granular_access:acl:name');
        }
    }
}