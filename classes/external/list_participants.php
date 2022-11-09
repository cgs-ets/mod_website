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
 *  External Web Service Template
 *
 * @package   mod_website
 * @category
 * @copyright 2020 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_website\external;

defined('MOODLE_INTERNAL') || die();

use external_function_parameters;
use external_value;
use external_single_structure;
use mod_website\website;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/website/lib.php');
require_once($CFG->dirroot . "/user/lib.php");
require_once($CFG->dirroot . "/user/externallib.php");

/**
 * Trait implementing the external function mod_website_delete_files
 */
trait list_participants {

    /**
     * Returns description of method parameters
     *
     */
    public static function list_participants_parameters() {
        return new external_function_parameters(
            array(
                'websiteid' => new external_value(PARAM_RAW, 'Instance ID'),
                'groupid' => new external_value(PARAM_RAW, 'Instance ID'),
            )
        );
    }

    public static function list_participants($websiteid, $groupid) {
        global $COURSE, $DB;

        $context = \context_course::instance($COURSE->id);
        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters(
            self::list_participants_parameters(),
            array(
                'websiteid' => $websiteid,
                'groupid' => $groupid
            )
        );
        
        $sql = "SELECT u.id as userid, u.firstname, u.lastname, gf.* FROM mdl_website_sites as gf
                INNER JOIN mdl_user as u ON gf.userid = u.id
                WHERE websiteid = :websiteid;";

        $results = $DB->get_records_sql($sql, array('websiteid' => $websiteid));
        $participants;
        foreach ($results as $record) {
            $participant = new \stdClass();
            $participant->userid = $record->userid;
            $participant->fullname = $record->firstname . ' ' . $record->lastname;
            $siteurl = new \moodle_url('/mod/website/site.php', array('site' => $record->id));
            $participant->siteurl = $siteurl->out();
            $website = new Website();
            list($participant->grade, $participant->comment) = $website->get_grade_comments($websiteid, $record->userid);
            $participants[] = $participant;
        }

        return array('users' => json_encode($participants));
    }

    /**
     * Describes the structure of the function return value.
     * @return external_single_structures
     */
    public static function list_participants_returns() {

        return new external_single_structure(array(
            'users' => new external_value(PARAM_RAW, 'ID of the user'),
        ));
    }
}
