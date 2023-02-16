<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Library of interface functions and constants.
 *
 * @package     mod_website
 * @copyright   2022 Michael Vangelovski <michael.vangelovski@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 /** Include required files */
require_once($CFG->libdir.'/filelib.php');

use mod_website\utils;

define('WEBSITE_GRADING_STATUS_GRADED', 'graded');
define('WEBSITE_GRADING_STATUS_NOT_GRADED', 'notgraded');

/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
function website_supports($feature) {
    switch ($feature) {
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        //case FEATURE_ADVANCED_GRADING:
        //    return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the mod_website into the database.
 *
 * Given an object containing all the necessary data, (defined by the form
 * in mod_form.php) this function will create a new instance and return the id
 * number of the instance.
 *
 * @param object $moduleinstance An object from the form.
 * @param mod_website_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function website_add_instance($moduleinstance, $mform = null) {
    global $DB, $USER;

    //echo "<pre>";
    //var_export($moduleinstance);
    //var_export($mform);
    //exit;

    // Template
    $templatesiteid = 0;
    if (!empty($moduleinstance->useexistingurl)) {
        $regex = '/\/mod\/website\/site\.php\?site\=(\d+)/';
        preg_match($regex, $moduleinstance->useexistingurl, $matches);
        if (empty($matches) || count($matches) < 2) {
            echo "Template error"; 
            return;
        }
        $templatesiteid = $matches[1];
    }

    // Create the website record.
    $moduleinstance->timecreated = time();
    $moduleinstance->distgroups = isset($moduleinstance->distgroups) ? $moduleinstance->distgroups : ['00_everyone'];
    $moduleinstance->groups = json_encode($moduleinstance->distgroups);
    $moduleinstance->id = $DB->insert_record('website', $moduleinstance);

    // Create the sites within this activity based on distribution.
    if ($moduleinstance->distribution === '0') { 
        // Single site.
        $website = new \mod_website\website();
        $website->create_site(
            array(
                'websiteid' => $moduleinstance->id,
                'cmid' => $moduleinstance->coursemodule,
                'creatorid' => $USER->id,
                'name' => $moduleinstance->name,
            ),
            $templatesiteid
        );
    } else if ($moduleinstance->distribution === '1') { 
        // Site per student.
        $students = utils::get_students_from_groups($moduleinstance->distgroups, $moduleinstance->course);
        $website = new \mod_website\website();
        $website->create_sites_for_students(
            $students, 
            array(
                'websiteid' => $moduleinstance->id,
                'cmid' => $moduleinstance->coursemodule,
                'creatorid' => $USER->id,
                'name' => $moduleinstance->name,
            ),
            $templatesiteid
        );
    } else if ($moduleinstance->distribution === '2') {
        // Page per student.
        // If a template URL has been supplied, check if a speciic page is nominated.
        $templatepageid = 0;
        if (!empty($moduleinstance->useexistingurl)) {
            $regex = '/\/mod\/website\/site\.php\?site\=(\d+)&page\=(\d+)/';
            preg_match($regex, $moduleinstance->useexistingurl, $matches);
            if (count($matches) == 3) {
                $templatepageid = $matches[2];
            } else {
                // Use homepage if only siteid is provided.
                $templatesite = new \mod_website\site($templatesiteid);
                $templatepageid = $templatesite->homepageid;
            }
        }
        $students = utils::get_students_from_groups($moduleinstance->distgroups, $moduleinstance->course);
        $website = new \mod_website\website();
        $website->create_pages_for_students(
            $students, 
            array(
                'websiteid' => $moduleinstance->id,
                'cmid' => $moduleinstance->coursemodule,
                'creatorid' => $USER->id,
                'name' => $moduleinstance->name,
            ),
            $templatepageid
        );
    }

    website_grade_item_update($moduleinstance);
    
    return $moduleinstance->id;
}

/**
 * Updates an instance of the mod_website in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param object $moduleinstance An object from the form in mod_form.php.
 * @param mod_website_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function website_update_instance($moduleinstance, $mform = null) {
    global $DB, $USER;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;
    $moduleinstance->distgroups = isset($moduleinstance->distgroups) ? $moduleinstance->distgroups : ['00_everyone'];
    if (empty($moduleinstance->distgroups)) {
        $website = new \mod_website\website($moduleinstance->id, $moduleinstance->coursemodule);
        $moduleinstance->groups = json_encode($website->get_groups());
    } else {
        $moduleinstance->groups = json_encode($moduleinstance->distgroups);
    }

    // Once set, distribution cannot be changed.
    $dist = $moduleinstance->distribution;
    unset($moduleinstance->distribution);

    // Sync student sites.
    if ($dist == '1') {
        utils::sync_student_sites($moduleinstance->id, $moduleinstance->distgroups, $moduleinstance->course, $moduleinstance->coursemodule, $USER->id, $moduleinstance->name);
    }

    $DB->update_record('website', $moduleinstance);

    return true; 
}

/**
 * Removes an instance of the mod_website from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function website_delete_instance($id) {
    global $DB;

    $website = $DB->get_record('website', array('id' => $id));
    if (!$website) {
        return false;
    }

    $DB->delete_records('website', array('id' => $id));

    website_grade_item_delete($website);
    return true;
}

/**
 * Is a given scale used by the instance of mod_website?
 *
 * This function returns if a scale is being used by one mod_website
 * if it has support for grading and scales.
 *
 * @param int $moduleinstanceid ID of an instance of this module.
 * @param int $scaleid ID of the scale.
 * @return bool True if the scale is used by the given mod_website instance.
 */
function website_scale_used($moduleinstanceid, $scaleid) {
    global $DB;

    if ($scaleid && $DB->record_exists('website', array('id' => $moduleinstanceid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of mod_website.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale.
 * @return bool True if the scale is used by any mod_website instance.
 */
function website_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('website', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given mod_website instance.
 *
 * Needed by {@see grade_update_mod_grades()}.
 *
 * @category grade
 * @uses GRADE_TYPE_VALUE
 * @uses GRADE_TYPE_NONE
 * @param object $website object with extra cmidnumber
 * @param array|object $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function website_grade_item_update($website, $grades = null) {
    global $CFG;

    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir . '/gradelib.php');
    }

    if (property_exists($website, 'cmidnumber')) { // May not be always present.
        $params = array('itemname' => $website->name, 'idnumber' => $website->cmidnumber);
    } else {
        $params = array('itemname' => $website->name);
    }

    if (!isset($website->courseid)) {
        $website->courseid = $website->course;
    }

    if ($website->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $website->grade;
        $params['grademin'] = 0;
    } else if ($website->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = -$website->grade;

        // Make sure current grade fetched correctly from $grades
        $currentgrade = null;
        if (!empty($grades)) {
            if (is_array($grades)) {
                $currentgrade = reset($grades);
            } else {
                $currentgrade = $grades;
            }
        }

        // When converting a score to a scale, use scale's grade maximum to calculate it.
        if (!empty($currentgrade) && $currentgrade->rawgrade !== null) {
            $grade = grade_get_grades($website->course, 'mod', 'website', $website->id, $currentgrade->userid);
            $params['grademax'] = reset($grade->items)->grademax;
        }
    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT; // Allow text comments only.
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    } else if (!empty($grades)) {
        // Need to calculate raw grade (Note: $grades has many forms).
        if (is_object($grades)) {
            $grades = array($grades->userid => $grades);
        } else if (array_key_exists('userid', $grades)) {
            $grades = array($grades['userid'] => $grades);
        }
        foreach ($grades as $key => $grade) {
            if (!is_array($grade)) {
                $grades[$key] = $grade = (array) $grade;
            }
            //check raw grade isnt null otherwise we erroneously insert a grade of 0
            if ($grade['rawgrade'] !== null) {
                $grades[$key]['rawgrade'] = ($grade['rawgrade'] * $params['grademax'] / 100);
            } else {
                // Setting rawgrade to null just in case user is deleting a grade.
                $grades[$key]['rawgrade'] = null;
            }
        }
    }

    return grade_update('/mod/website', $website->course, 'mod', 'website', $website->id, 0, $grades, $params);
}


function website_get_user_grades_for_gradebook($instance, $userid = 0) {
    global $DB;
    $grades = array();
    $adminconfig = get_config('website');

    $gradebookplugin = website_is_gradebook_feedback_enabled();

    if ($userid) {
        $where = ' WHERE u.id = :userid ';
    } else {
        $where = ' WHERE u.id != :userid ';
    }
    $params = [
        'websiteid1' => $instance->id,
        'websiteid2' => $instance->id,
        'websiteid3' => $instance->id,
        'userid' => $userid
    ];

    $graderesults = $DB->get_recordset_sql('SELECT u.id as userid, s.timemodified as datesubmitted,
                                            g.grade as rawgrade, g.timemodified as dategraded, g.grader as usermodified,                                             fc.commenttext as feedback, fc.commentformat as feedbackformat
                                            FROM mdl_user as u
                                            LEFT JOIN mdl_website_sites as s
                                            ON u.id = s.userid and s.websiteid = :websiteid1
                                            JOIN mdl_website_grades as g
                                            ON u.id = g.userid and g.websiteid = :websiteid2
                                            JOIN mdl_website_feedback as fc
                                            ON fc.websiteid = :websiteid3 AND fc.grade = g.id' .
        $where, $params);

    foreach ($graderesults as $result) {
        $gradingstatus = website_get_grading_status($result->userid, $instance->id);
        if ($gradingstatus == WEBSITE_GRADING_STATUS_GRADED) {
            $gradebookgrade = clone $result;
            // Now get the feedback.
            $gradebookgrade->feedback = $result->feedback;
            $gradebookgrade->feedbackformat = $result->feedbackformat;
            $grades[$gradebookgrade->userid] = $gradebookgrade;
        }
    }
    $graderesults->close();
    return $grades;
}

function website_is_gradebook_feedback_enabled() {
    // Get default grade book feedback plugin.
    $adminconfig = get_config('website');
    $gradebookplugin = $adminconfig->feedback_plugin_for_gradebook;
    $gradebookplugin = str_replace('assignfeedback_', '', $gradebookplugin);

    // Check if default gradebook feedback is visible and enabled.
    $gradebookfeedbackplugin = website_get_feedback_plugin_by_type($gradebookplugin);

    if (empty($gradebookfeedbackplugin)) {
        return false;
    }

    if ($gradebookfeedbackplugin->is_visible() && $gradebookfeedbackplugin->is_enabled()) {
        return true;
    }

    // Gradebook feedback plugin is either not visible/enabled.
    return false;
}

function website_get_feedback_plugin_by_type($type) {
    return website_get_plugin_by_type('assignfeedback', $type);
}

function website_get_plugin_by_type($subtype, $type) {
    $shortsubtype = substr($subtype, strlen('assign'));
    $name = $shortsubtype . 'plugins';
    if ($name != 'feedbackplugins' && $name != 'submissionplugins') {
        return null;
    }
    $pluginlist = $name;
    foreach ($pluginlist as $plugin) {
        if ($plugin->get_type() == $type) {
            return $plugin;
        }
    }
    return null;
}

function website_get_grading_status($userid, $website) {
    global $DB;
    $sql = "SELECT * FROM mdl_website_grades WHERE userid = {$userid}
            AND websiteid = {$website};";
    $grades = $DB->get_record_sql($sql);

    if ($grades) {
        return WEBSITE_GRADING_STATUS_GRADED;
    } else {
        return WEBSITE_GRADING_STATUS_NOT_GRADED;
    }
}

/**
 * Delete grade item for given mod_website instance.
 *
 * @param stdClass $moduleinstance Instance object.
 * @return grade_item.
 */
function website_grade_item_delete($moduleinstance) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    $DB->delete_records('website_grades', array('websiteid' => $website->id));
    $DB->delete_records('website_feedback', array('website' => $website->id));
    
    return grade_update('/mod/website', $moduleinstance->course, 'mod', 'website',
                        $moduleinstance->id, 0, null, array('deleted' => 1));
}


/**
 * Update the grade(s) for the supplied user.
 * @param stdClass  $website
 * @param int $userid
 * @param bool $nullifnone
 */
function website_update_grades($website, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if ($website->grade == 0) {
        website_grade_item_update($website);
    } else if ($grades = website_get_user_grades_for_gradebook($website, $userid)) {

        foreach ($grades as $k => $v) {
            if ($v->rawgrade == -1) {
                $grades[$k]->rawgrade = null;
            }
        }

        website_grade_item_update($website, $grades);
    } else {
        website_grade_item_update($website);
    }
}

/**
 * Returns the lists of all browsable file areas within the given module context.
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@see file_browser::get_file_info_context_module()}.
 *
 * @package     mod_website
 * @category    files
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return string[].
 */
function website_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for mod_website file areas.
 *
 * @package     mod_website
 * @category    files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info Instance or null if not found.
 */
function website_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}


/**
 * Serves the plugin attachments.
 *
 * @package     mod_website
 * @category    files
 * 
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function mod_website_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_website/$filearea/$relativepath";



    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, false, $options);
}

/**
 * Implements callback user_preferences.
 *
 * Used in {@see core_user::fill_preferences_cache()}
 *
 * @return array
 */
function mod_website_user_preferences() {
    $preferences = array();
    $preferences['mod_website_editmode'] = array(
        'type' => PARAM_INT,
        'null' => NULL_NOT_ALLOWED,
        'default' => 0,
        'choices' => array(0, 1),
        'permissioncallback' => function($user, $preferencename) {
            global $USER;
            return $user->id == $USER->id;
        }
    );
    return $preferences;
}


/**
 * A convenience function to turn edit mode on.
 *
 * @return void
 */
function website_turn_editing_on() {
    set_user_preference('mod_website_editmode', 1);
}

/**
 * A convenience function to turn edit mode off.
 *
 * @return void
 */
function website_turn_editing_off() {
    set_user_preference('mod_website_editmode', 0);
}

/**
 * A convenience function to check whether the user currently has editing on in their preferences.
 *
 * @return bool
 */
function website_is_editing_on() {
    return get_user_preferences('mod_website_editmode');
}