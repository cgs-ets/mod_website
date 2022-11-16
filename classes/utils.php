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

use mod_website\website;

/**
 * Provides utility functions for this plugin.
 *
 * @package   mod_website
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class utils {


    public static function is_grader() {
        global $COURSE;

        $context = \context_course::instance($COURSE->id);
        if (has_capability('moodle/grade:manage', $context)) {
            return true;
        }
        return false;
    }

    public static function is_user_mentor_of_student($userid, $studentuserid) {
        $mentors = static::get_users_mentors($userid, 'id');
        return in_array($userid, $mentors);
    }

    public static function get_users_mentors($userid, $field = 'username') {
        global $DB;

        $mentors = array();
        $mentorssql = "SELECT u.*
                         FROM {role_assignments} ra, {context} c, {user} u
                        WHERE c.instanceid = :menteeid
                          AND c.contextlevel = :contextlevel
                          AND ra.contextid = c.id
                          AND u.id = ra.userid";
        $mentorsparams = array(
            'menteeid' => $userid,
            'contextlevel' => CONTEXT_USER
        );
        if ($mentors = $DB->get_records_sql($mentorssql, $mentorsparams)) {
            $mentors = array_column($mentors, $field);
        }
        return $mentors;
    }

    public static function should_clean_content($website) {
        // Don't clean teacher sites.
        if ($website->distribution === '0') {
            return true; //noclean
        }
        // Clean student sites.
        return false;
    }

    /**
     * Helper function to get the students enrolled
     *
     * @param int $courseid
     * @return int[]
     */
    public static function get_enrolled_students($courseid) {
        global $DB;
        $context = \context_course::instance($courseid);
        
        // 5 is student.
        $studentroleid = $DB->get_field('role', 'id', array('shortname'=> 'student'));
        $users = get_role_users($studentroleid, $context, false, 'u.id, u.username, u.firstname, u.lastname', 'u.lastname'); //last param is sort by.

        return array_map('intval', array_column($users, 'id'));
    }


    public static function get_students_from_groups($groups, $courseid) {
        $students = array();
        if (! array_search('00_everyone', $groups) ) 
        {
            // Specific groups or groupings.
            foreach ( $groups as $groupselection ) {
                $split = explode('_', $groupselection);
                if (intval($split[0]) === 0) {
                    continue;
                }
                if ($split[1] === 'group') {
                    $students = array_merge($students, array_column(groups_get_members($split[0], 'u.id'), 'id'));
                }
                if ($split[1] === 'grouping') {
                    $students = array_merge($students, array_column(groups_get_grouping_members($split[0], 'u.id')));
                }
            }
        }
        else
        {
            // Everyone - Get all students in course.
            $students = static::get_enrolled_students($courseid);
        }
        return $students;
    }

    public static function sync_student_sites($websiteid, $groups, $courseid, $cmid, $creatorid, $newname) {
        $newstudents = static::get_students_from_groups($groups, $courseid);
        $website = new Website($websiteid, $cmid);
        $website->load_sites();
        foreach ($website->get_sites() as $site) {
            if ( $i = array_search($site->get_userid(), $newstudents) ) {
                unset($newstudents[$i]);
                continue;
            } else {
                //$site->delete();
            }
        }
        // Create the left over.
        $website->create_sites_for_students($newstudents, array(
            'websiteid' => $websiteid,
            'cmid' => $cmid,
            'creatorid' => $creatorid,
            'name' => $newname,
        ));
    }
}