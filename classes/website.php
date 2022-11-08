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

require_once($CFG->libdir.'/gradelib.php');

use mod_website\site;
use mod_website\utils;

/**
 * Provides utility functions for this plugin.
 *
 * @package   mod_website
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class Website {

    const TABLE = 'website';
    const TABLE_SITES = 'website_sites';
    const TABLE_PAGES = 'website_site_pages';
    const TABLE_SECTIONS = 'website_site_sections';
    const TABLE_BLOCKS = 'website_site_blocks';

    private $cmid;
    private $courseid;

    /**
     * Create an instance of this class.
     *
     * @param int $id If set, this is the id of an existing record, used to load the data.
     */
    public function __construct($cmid = 0, $courseid = 0) {
        global $CFG;

        $this->cmid = $cmid;
        $this->courseid = $courseid;
    }

    public function render_student_sites_table() {
        global $OUTPUT;

        $students = utils::get_enrolled_students($this->courseid);
        foreach ($students as $studentid) {
            $student = \core_user::get_user($studentid);
            $picture = $OUTPUT->user_picture($student, array(
                'course' => $this->courseid,
                'includefullname' => true, 
                'class' => 'userpicture',
            ));

            $grading_info = grade_get_grades($this->courseid, 'mod', 'website', $this->cmid, [9999999]);
            $grade = array_pop($grading_info->items[0]->grades)->grade;
            $beengraded = $grade ? true : false;
            $gradeurl = new \moodle_url('/mod/website/view_grading_app.php?', array(
                'id' => $this->cmid,
                'action' => 'grader',
                'userid' => $studentid
            ));

            // Get the site instance.
            $site = new Site();
            $site->read_for_studentid($studentid);
            $siteurl = new \moodle_url('/mod/website/site.php', array('site' => $site->get_id()));
            
            $data['students'][] = [
                'picture' => $picture,
                'fullname' => fullname($student),
                'studentid' => $student->id,
                'studentemail' => $student->email,
                'gradeurl' => $gradeurl,
                'beengraded' => $beengraded,
                'grade' => $grade,
                'siteurl' => $siteurl,
            ];
        }

        echo $OUTPUT->render_from_template('mod_website/students_table', $data);
    }

}