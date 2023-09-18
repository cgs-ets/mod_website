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
use mod_website\permissions;
use mod_website\copying;

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

    private $cmid;
    private $data;
    private $sites;

    /**
     * Create an instance of this class.
     *
     * @param int $id If set, this is the id of an existing record, used to load the data.
     */
    public function __construct($id = 0, $cmid = 0) {
        global $CFG;

        $this->cmid = $cmid;

        if ($id > 0) {
            return $this->read($id);
        }
    }



    /**
     * Load the data from the DB.
     *
     * @param $id
     * @return static
     */
    final public function read($id) {
        global $DB;

        $this->data = $DB->get_record(static::TABLE, array('id' => $id), '*', IGNORE_MULTIPLE);

        return $this;
    }

    public function load_sites() {
        global $DB;

        $this->sites = array();
    
        $sites = $DB->get_records(static::TABLE_SITES, array(
            'websiteid' => $this->data->id,
            'cmid' => $this->cmid,
            'deleted' => 0,
        ));

        // Get the site instances.
        foreach($sites as $site) {
            $site = new Site($site->id);
            $this->sites[] = $site;
        }
    }

    
    /**
     * Load the data from the DB.
     *
     * @return static
     */
    final public function load_sites_for_studentids($studentids) {
        global $DB;

        $this->sites = array();

        list($insql, $inparams) = $DB->get_in_or_equal($studentids);
        $sql = "SELECT * 
                FROM {" . static::TABLE_SITES . "}
                WHERE websiteid = {$this->data->id}
                AND cmid = {$this->cmid}
                AND deleted = 0
                AND userid {$insql}";


        $sites = $DB->get_records_sql($sql, $inparams);

        // Get site instances.
        foreach($sites as $site) {
            $site = new Site($site->id);
            $this->sites[] = $site;
        }

        return $this;
    }

    public function render_sites_table($showgrading = true) {
        global $OUTPUT;

        $data = array(
            'showgrading' => $showgrading,
            'students' => array()
        );

        foreach ($this->sites as $site) {
            $studentid = $site->get_userid();
            $student = \core_user::get_user($studentid);
            $picture = $OUTPUT->user_picture($student, array(
                'course' => $this->data->course,
                'includefullname' => true, 
                'class' => 'userpicture',
            ));

            $siteurl = new \moodle_url('/mod/website/site.php', array('site' => $site->get_id()));
            $studentdata = [
                'picture' => $picture,
                'fullname' => fullname($student),
                'studentid' => $student->id,
                'studentemail' => $student->email,
                'siteurl' => $siteurl,
            ];

            if ($showgrading) {
                $grading_info = grade_get_grades($this->data->course, 'mod', 'website', $this->cmid, array($studentid));
                $grade = null;
                if ($grading_info->items) {
                    if ($grading_info->items[0]->grades)  {
                        $grade = array_pop($grading_info->items[0]->grades)->grade;
                    }
                }
                $beengraded = $grade ? true : false;
                $gradeurl = new \moodle_url('/mod/website/view_grading_app.php', array(
                    'id' => $this->cmid,
                    'action' => 'grader',
                    'userid' => $studentid
                ));

                $studentdata['gradeurl'] = $gradeurl;
                $studentdata['beengraded'] = $beengraded;
                $studentdata['grade'] = $grade;
            }

            $data['students'][] = $studentdata;
        }

        echo $OUTPUT->render_from_template('mod_website/students_table', $data);
    }


    public function view_grading_app($websiteid, $userid) {
        global $OUTPUT, $DB, $CFG;

        // Get the user record.
        $user = $DB->get_record('user', array('id' => $userid));

        // Get the user's site.
        $site = new Site();
        $site->read_for_studentid($websiteid, $userid);
        $siteurl = new \moodle_url('/mod/website/site.php', array('site' => $site->get_id()));

        // Grade details.
        list($gradegiven, $commentgiven) = $this->get_grade_comments($this->data->id, $userid);

        // Get data from gradebook.
        $sql = "SELECT * FROM mdl_grade_grades as gg
                WHERE itemid = (
                    SELECT id as itemid FROM mdl_grade_items
                    WHERE iteminstance = {$this->data->id}
                    AND itemtype = 'mod' AND itemmodule = 'website' 
                )
                AND userid = {$userid}"; 
        $gg = $DB->get_record_sql($sql);

        $lockedoroverriden = false;
        $gradefromgradebook = 0;
        $gradebookurl = '';
        if ($gg && ($gg->locked != "0" || $gg->overridden != "0")) {
            $lockedoroverriden = true;
            $gradefromgradebook = $gg->finalgrade;
            $gradebookurl = new \moodle_url($CFG->wwwroot . '/grade/report/grader/index.php', ['id' => $this->data->course]);
        }

        $gradeurl = new \moodle_url('/mod/website/view_grading_app.php', array(
            'id' => $this->cmid,
            'action' => 'grader',
            'userid' => $userid,
        ));

        $coursecontext = \context_course::instance($this->data->course);

        $data = [
            'userid' => $userid,
            'courseid' => $this->data->course,
            'showuseridentity' => true,
            'coursename' => $coursecontext->get_context_name(),
            'cmid' => $this->cmid,
            'name' => $this->data->name,
            'caneditsettings' => false,
            'actiongrading' => 'grading',
            'viewgrading' => get_string('viewgrading', 'website'),
            'websiteid' => $this->data->id,
            'usersummary' => $OUTPUT->user_picture($user, array('course' => $this->data->course, 'includefullname' => true, 'class' => 'userpicture')),
            'useremail' => $user->email,
            'siteurl' =>   $siteurl,
            'maxgrade' => $this->data->grade,
            'gradegiven' => $gradegiven,
            'graded' => ($gradegiven == '') ? false : true,
            'commentgiven' => $commentgiven,
            'users' => $this->get_list_participants($this->data->id),
            'lockedoroverriden' => $lockedoroverriden,
            'finalgrade' => number_format($gradefromgradebook, 2),
            'gradebookurl' => $gradebookurl,
            'display' => true,
            'contextid' => $coursecontext->id,
            'gradeurl' => $gradeurl
        ];

        //print_object($data); exit;
        echo $OUTPUT->render_from_template('mod_website/grading_app', $data);
    }

    public function get_id() {
        return isset($this->data->id) ? $this->data->id : null;
    }

    public function get_groups() {
        $distgroups = isset($this->data->groups) ? json_decode($this->data->groups) : [];
        $distgroups = empty($distgroups) ? ['00_everyone'] : $distgroups;
        return $distgroups;
    }

    public function get_course() {
        return $this->data->course;
    }

    public function get_name() {
        return $this->data->name;
    }

    public function get_sites() {
        return $this->sites;
    }

    public function get_distribution() {
        return isset($this->data->distribution) ? $this->data->distribution : null;
    }

    public function get_exhibition() {
        return isset($this->data->exhibition) ? $this->data->exhibition : null;
    }

    public function get_grade_comments($websiteid, $userid) {
        global $DB;

        $sql = "SELECT comments.commenttext as comment, grades.grade as gradevalue 
                FROM mdl_website_grades as grades
                INNER JOIN mdl_website_feedback as comments 
                ON grades.id = comments.grade
                WHERE grades.userid = :userid 
                AND grades.websiteid = :instanceid;";

        $grading = $DB->get_record_sql($sql, array(
            'userid' => $userid,
            'instanceid' => $websiteid,
        ));

        if ($grading) {
            return array($grading->gradevalue, $grading->comment);
        } else {
            return array('', '');
        }
    }

    private function get_list_participants($websiteid) {
        global $DB;

        $sql = "SELECT u.id, CONCAT(u.firstname,' ', u.lastname) as fullname, gf.* FROM mdl_website_sites as gf
                JOIN mdl_user as u ON gf.userid = u.id
                WHERE websiteid = :websiteid ORDER BY u.lastname;";

        $participants = $DB->get_records_sql($sql, array('websiteid' => $websiteid));
        $users = [];

        foreach ($participants as $participant) {
            $user = new \stdClass();
            $user->userid = $participant->userid;
            $user->fullname = $participant->fullname;
            list($user->grade, $user->comment) = $this->get_grade_comments($websiteid, $participant->userid);
            $users[] = $user;
        }

        return $users;
    }


    public function create_site($data, $templatesiteid = 0) {
        // Single teacher site.
        $data = (object) $data;
        $sitedata = array(
            'websiteid' => $data->websiteid,
            'cmid' => $data->cmid,
            'creatorid' => $data->creatorid,
            'userid' => $data->creatorid,
            'title' => $data->name,
            'siteoptions' => '',
        );
        $site = new \mod_website\site();
        if (!$templatesiteid) {
            $site->create($sitedata);
        } else {
            $site->create_from_template($sitedata, $templatesiteid, true);
        }
    }


    public function create_sites_for_students($students, $data, $templatesiteid = 0) {
        // Copies for students.
        foreach ($students as $studentid) {
            $data = (object) $data;
            $sitedata = array(
                'websiteid' => $data->websiteid,
                'cmid' => $data->cmid,
                'creatorid' => $data->creatorid,
                'userid' => $studentid,
                'title' => $data->name,
                'siteoptions' => '',
            );
            $site = new \mod_website\site();
            if (!$templatesiteid) {
                $site->create($sitedata);
            } else {
                $site->create_from_template($sitedata, $templatesiteid, true);
            }
        }
    }

    public function create_pages_for_students($students, $data, $templatepageid = 0) {
        $data = (object) $data;
        $oldpage = new \mod_website\page($templatepageid);
        $oldsite = new \mod_website\site($oldpage->get_siteid()); 

        // Create the initial site.
        $this->create_site($data);
        $site = new \mod_website\site(); 
        $site->read_from_websiteid($data->websiteid);
        if (empty($site->get_id())) {
            return false;
        }

        // Add homepage into menu.
        $menu = new \mod_website\menu($site->menuid);
        $menudata[0] = array (
            'id' => $site->homepageid,
            'attributes' => '',
            'children' => array(),
        );
        $menu->update_menu_from_array($menudata);

        // Create a page for each student.
        foreach ($students as $studentid) {
            $newpage = new \mod_website\page();
            $user = \core_user::get_user($studentid);
            utils::load_user_display_info($user);
            // Page is based on a template.
            if ($templatepageid) {
                // Copy the page.
                $pagecopy = copying::copy_page($templatepageid, $site->get_id());
                if (empty($pagecopy)) {
                    return false;
                }
                $newpage = new \mod_website\page($pagecopy);
                $newpage->set('title', $user->fullname);
                $newpage->update();

                // Copy components.
                $pagecopies = array($templatepageid => $pagecopy);
                $sectioncopies = copying::copy_page_sections($templatepageid, $site->get_id());
                $blockcopies = copying::copy_page_blocks($templatepageid, $site->get_id());

                //Copy files
                $oldcontext = \context_module::instance($oldsite->get_cmid());
                $newcontext = \context_module::instance($site->get_cmid());
                copying::copy_page_files($pagecopies, $oldcontext, $newcontext);
                copying::copy_block_files($blockcopies, $oldcontext, $newcontext);
  
                // Relink the page, sections and blocks.
                copying::update_page_section_references($pagecopies, $sectioncopies);
                copying::update_section_block_references($sectioncopies, $blockcopies);

            } else {
                // A blank page.
                $newpage = new \mod_website\page();
                $newpage->create([
                    'siteid' => $site->get_id(),
                    'title' => $user->fullname,
                    'sections' => '',
                ]);
            }

            // Add student page to menu.
            $menudata = $menu->menu_to_array();
            $menudata[0]['children'][] = array (
                'id' => $newpage->get_id(),
                'attributes' => '',
                'children' => array(),
            );
            $menu->update_menu_from_array($menudata);

            // Set the page edit permissions - resourcetype, resourcekey, userid.
            permissions::create('Page', $newpage->get_id(), $studentid);
        }

        // Create a block on the homepage that contains links to each student page.
        
    }

}