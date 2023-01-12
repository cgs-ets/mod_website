<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   mod_website
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_website;

defined('MOODLE_INTERNAL') || die();

/**
 * Provides utility functions for this plugin.
 *
 * @package   mod_website
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class permissions {

    const TABLE_PERMISSIONS = 'website_permissions';
    
    public static function sync_permission_selections($resourcetype, $resourcekey, $data) {
        if ($data->editorstype == 'groups') 
        {
            static::create_edit_permissions_groups($resourcetype, $resourcekey, $data->sharinggroups);
        } 
        elseif ($data->editorstype == 'roles')
        {
            static::create_edit_permissions_roles($resourcetype, $resourcekey, $data->courseid, $data->sharingroles);
        }
        elseif ($data->editorstype == 'users')
        {
            static::create_edit_permissions_users($resourcetype, $resourcekey, $data->sharingusers);
        }
        elseif ($data->editorstype == 'removeall')
        {
            static::remove_all_edit_permissions($resourcetype, $resourcekey);
        }
    }

    public static function create_edit_permissions_groups($resourcetype, $resourcekey, $list) {
        // Convert selected groups into a list of users. 
        $users = array();
        foreach ( $list as $groupselection ) {
            $split = explode('_', $groupselection);
            if (intval($split[0]) === 0) {
                continue;
            }
            $members = array();
            if ($split[1] === 'group') {
                $members = groups_get_members($split[0], 'u.id');
            }
            if ($split[1] === 'grouping') {
                $members = groups_get_grouping_members($split[0], 'u.id');
            }
            $memberids = array_column($members, 'id');
            $users = array_merge($users, $memberids);
        }
        
        static::replace_edit_permissions_users($resourcetype, $resourcekey, $users);
    }

    public static function create_edit_permissions_roles($resourcetype, $resourcekey, $courseid, $list) {
        // Convert selected roles into a list of users. 
        $users = array();
        foreach ( $list as $roleselection ) {
            $split = explode('_', $roleselection);
            if (intval($split[0]) === 0) {
                continue;
            }
            $context = \context_course::instance($courseid);
            $roleusers = get_role_users($split[0], $context, false, 'u.id', 'u.id'); //last param is sort by.
            $roleusersids = array_column($roleusers, 'id');
            $users = array_merge($users, $roleusersids); 
        }

        static::replace_edit_permissions_users($resourcetype, $resourcekey, $users);
    }

    public static function create_edit_permissions_users($resourcetype, $resourcekey, $list) {
        // Convert selected users into a list of users. 
        $users = array();
        foreach ( $list as $userselection ) {
            $split = explode('_', $userselection);
            if (intval($split[0]) === 0) {
                continue;
            }
            $users[] = $split[0];
        }

        static::replace_edit_permissions_users($resourcetype, $resourcekey, $users);
    }

    public static function replace_edit_permissions_users($resourcetype, $resourcekey, $users) {
        global $DB, $USER;

        // Fundamental properties.
        $data = new \stdClass();
        $data->permissiontype = 'Edit';
        $data->resourcetype = $resourcetype;
        $data->resourcekey = $resourcekey;

        // Get existing permissions.
        $existing = array_column($DB->get_records(static::TABLE_PERMISSIONS, (array) $data, 'id', 'userid'), 'userid');

        // Create the new records.
        $data->ownerid = $USER->id;
        foreach ( $users as $user ) {
            // Check if it already exists.
            $key = array_search($user, $existing);
            if ($key !== false) {
                unset($existing[$key]);
                continue;
            }

            // Create an editing permission record for each user.
            $data->userid = $user;
            $DB->insert_record(static::TABLE_PERMISSIONS, $data);
        }

        // Delete old permissions.
        foreach ($existing as $todelete) {
            unset($data->ownerid);
            $data->userid = $todelete;
            $DB->delete_records(static::TABLE_PERMISSIONS, (array) $data);
        }
    }

    public static function remove_all_edit_permissions($resourcetype, $resourcekey) {
        global $DB;

        // Fundamental properties.
        $data = new \stdClass();
        $data->permissiontype = 'Edit';
        $data->resourcetype = $resourcetype;
        $data->resourcekey = $resourcekey;

        $DB->delete_records(static::TABLE_PERMISSIONS, (array) $data);
    }

    public static function create($resourcetype, $resourcekey, $userid) {
        global $DB, $USER;

        // Fundamental properties.
        $data = new \stdClass();
        $data->permissiontype = 'Edit';
        $data->resourcetype = $resourcetype;
        $data->resourcekey = $resourcekey;
        $data->userid = $userid;

        // Check if permission already exists
        $existing = $DB->get_records(static::TABLE_PERMISSIONS, (array) $data, 'id', 'userid');
        if ($existing) {
            return false;
        }

        // Create the new records.
        $data->ownerid = $USER->id;
        $DB->insert_record(static::TABLE_PERMISSIONS, $data);

        return true;
    }
   

}