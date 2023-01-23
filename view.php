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
use mod_website\website;

// Course module id.
$id = optional_param('id', 0, PARAM_INT);

// Activity instance id.
$w = optional_param('w', 0, PARAM_INT);

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

if ($website->distribution === '0') 
{
    // Get the single site instance.
    $site = new Site();
    $site->read_from_websiteid($website->id);
    $url = new moodle_url('/mod/website/site.php', array('site' => $site->get_id()));
    redirect($url->out(false));
    exit;
} 
else if ($website->distribution === '1') 
{
    if (utils::is_grader()) {
        // Get a list of sites (student copies) and print a table of links.
        echo $OUTPUT->header();
        $website = new Website($website->id, $cm->id);
        $website->load_sites();
        $website->render_sites_table();
        echo $OUTPUT->footer();
    } else {
        // Get and display the site for this user.
        $site = new Site();
        $site->read_for_studentid($website->id, $USER->id);
        if ($site->get_id()) {
            $url = new \moodle_url('/mod/website/site.php', array('site' => $site->get_id()));
            redirect($url->out(false));
            exit;
        } else {
            // Check if this is a mentor
            $mentees = utils::get_users_mentees($USER->id, 'id');
            if (count($mentees) > 1) {
                echo $OUTPUT->header();
                $website = new Website($website->id, $cm->id);
                $website->load_sites_for_studentids($mentees);
                $website->render_sites_table(false);
                echo $OUTPUT->footer();
            }
            else if (count($mentees) == 1) {
                $site = new Site();
                $site->read_for_studentid($website->id, $mentees[0]);
                $url = new \moodle_url('/mod/website/site.php', array('site' => $site->get_id()));
                redirect($url->out(false));
                exit;
            } else {
                echo $OUTPUT->header();
                notice(get_string('nopermissiontoview', 'mod_website'), new moodle_url('/course/view.php', array('id' => $course->id)));
                echo $OUTPUT->footer();
            }
        }
    }
}
else if ($website->distribution === '2') 
{
    // Get the site instance.
    $site = new Site();
    $site->read_from_websiteid($website->id);
    $url = new moodle_url('/mod/website/site.php', array('site' => $site->get_id()));
    redirect($url->out(false));
    exit;
}