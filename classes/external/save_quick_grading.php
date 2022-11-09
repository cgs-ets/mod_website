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

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/website/lib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Trait implementing the external function mod_save_quick_grading
 */
trait save_quick_grading {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
     */
    public static function save_quick_grading_parameters() {
        return new external_function_parameters(
            array(
                'grade' => new external_value(PARAM_RAW, 'A JSON with the user grading'),
            )
        );
    }

    public static function save_quick_grading($grade) {
        global $COURSE, $DB, $USER;

        $context = \context_course::instance($COURSE->id);

        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters(
            self::save_quick_grading_parameters(),
            array('grade' => $grade)
        );

        $grade = json_decode($grade);
        $sql = "SELECT * FROM mdl_website_grades  WHERE userid = :userid AND websiteid = :websiteid";

        $params = ['userid' => $grade->userid, 'websiteid' => $grade->websiteid];
        $current = $DB->get_record_sql($sql, $params);

        list($gradeval, $comment) = explode('&', $grade->formdata);
        $gradeval = str_replace('grade=', '', $gradeval);
        $comment = rawurldecode(str_replace('comment=', '', $comment));

        if (!$current) { // New entry.
            $data = new \stdClass();
            $data->websiteid = $grade->websiteid;
            $data->userid = $grade->userid;
            $data->timecreated = time();
            $data->timemodified = time();
            $data->grader = $USER->id;
            $data->grade = floatval($gradeval);
            $recordid = $DB->insert_record('website_grades', $data, true);

            if ($recordid != null) {
                $data = new \stdClass();
                $data->websiteid = $grade->websiteid;
                $data->grade = $recordid;
                $data->commenttext = $comment;
                $data->commentformat = 1;
                $commentfeedbackrecord = $DB->insert_record('website_feedback', $data, true);
            }
            $graderesult = new \stdClass();
            $graderesult->userid = $grade->userid;
            $graderesult->grade = $gradeval;
            $graderesult->comment = $comment;

            $saveresult[] = [
                'graderecordid' => $recordid,
                'commentrecorid' => $commentfeedbackrecord,
                'grade' => $graderesult,
                'CRUD' => 'Create'
            ];
        } else {

            // Update grade.
            $current->timemodified = time();
            $current->grade = $gradeval;
            $DB->update_record('website_grades', $current);

            // Update comment.
            $sql = "SELECT * 
                    FROM mdl_website_feedback
                    WHERE websiteid = :websiteid 
                    AND grade = :gradeid";
            $params = ['websiteid' => $grade->websiteid, 'gradeid' => $current->id];

            $currentcomment = $DB->get_record_sql($sql, $params);
            $currentcomment->commenttext = $comment;

            $DB->update_record('website_feedback', $currentcomment);
            $saveresult[] = [
                'grade' => $graderesult,
                'CRUD' => 'UPDATE'
            ];
        }

        $sql = "SELECT * FROM mdl_website WHERE id = {$grade->websiteid}";
        $websiteinstance = $DB->get_record_sql($sql);
        
        // Sync with gradebook.
        website_update_grades($websiteinstance, $grade->userid, true);

        return array(
            'saveresult' => json_encode($saveresult)
        );
    }

    /**
     * Describes the structure of the function return value.
     * Returns the URL of the file for the student
     * @return external_single_structure
     *
     */
    public static function save_quick_grading_returns() {
        return new external_single_structure(
            array(
                'saveresult' => new external_value(PARAM_RAW, 'Save result'),
            )
        );
    }
}
