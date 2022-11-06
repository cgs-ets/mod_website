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
 * Prints an instance of mod_website.
 *
 * @package     mod_website
 * @copyright   2022 Michael Vangelovski <michael.vangelovski@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

use mod_website\utils;
use mod_website\site;

// Course module id.
$id = optional_param('id', 0, PARAM_INT);

// Activity instance id.
$w = optional_param('w', 0, PARAM_INT);

// View/edit.
$mode = optional_param('mode', 'view', PARAM_TEXT);

if ($id) {
    $cm = get_coursemodule_from_id('website', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $website = $DB->get_record('website', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $website = $DB->get_record('website', array('id' => $w), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $website->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('website', $website->id, $course->id, false, MUST_EXIST);
}
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);
$event = \mod_website\event\course_module_viewed::create(array(
    'objectid' => $website->id,
    'context' => $modulecontext
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('website', $website);
$event->trigger();

$PAGE->set_url('/mod/website/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($website->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

// Check if single site or copy for each student.
if ($website->distribution) {
    if (utils::is_grader()) {
        // Get a list of sites (student copies) and print a table of links.
        echo "get list of copies";
    } else {
        // Get and display the site for this user.
        echo "get copy for user";
    }
} else {
    // Get the single site instance.
    $site = new Site();
    $site->read_from_websiteid($website->id);
    $url = new moodle_url('/mod/website/site.php', array('site' => $site->get_id(), 'mode' => $mode));
	redirect($url->out(false));
	exit;
}

echo $OUTPUT->header();

echo $OUTPUT->footer();