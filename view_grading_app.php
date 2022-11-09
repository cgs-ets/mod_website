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
 * Prints a particular instance of website
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_website
 * @copyright  2022 Michael Vangelovski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use mod_website\website;

$new = optional_param('forceview', 0, PARAM_INT);
$id = required_param('id', PARAM_INT);

$action = optional_param('action', array(), PARAM_ALPHA);
$fromsummary = optional_param('fromsummary', '', PARAM_ALPHA);
$userid = required_param('userid', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'website');

$website = new Website($cm->instance, $cm->id);

$coursecontext = context_course::instance($course->id);
$PAGE->set_context($coursecontext); // Every page needs a context.

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('moodle/course:manageactivities', $context);

$url = new moodle_url('/mod/website/view_grading_app.php', array('id' => $cm->id));

$PAGE->set_url($url);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(format_string($website->get_name()));
$PAGE->add_body_class('path-mod-website');
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->set_pagelayout('embedded');
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/website/website.css', array('nocache' => rand())));

// Output starts here.
echo $OUTPUT->header();

$website->view_grading_app($userid);

// Finish the page.
echo $OUTPUT->footer();

