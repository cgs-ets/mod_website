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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Site logs
 *
 * @package    mod_website
 * @copyright  2025 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 require(__DIR__.'/../../config.php');
 require_once(__DIR__.'/lib.php');


use mod_website\utils;
use mod_website\site;
use mod_website\page;

 // Course module id.
$siteid = required_param('site', PARAM_INT);
$pageid = optional_param('page', 0, PARAM_INT);
$mode = optional_param('mode', '', PARAM_TEXT);

// Get the single site instance.
$site = new Site($siteid);
$page = new Page($pageid);


$cm = get_coursemodule_from_id('website', $site->get_cmid(), 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$website = $DB->get_record('website', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);


// Set up page.
$modulecontext = context_system::instance();
$url = new moodle_url('/mod/website/site.php', array(
    'site' => $siteid,
    'page' => $pageid,
));

$website->url = $url->out(false);

$PAGE->set_url($url);
$PAGE->set_title('Website Logs | ' . format_string($page->get_title()));
$PAGE->set_heading('Website Logs | ' . format_string($page->get_title()));
$PAGE->set_context($modulecontext);

// Navigation.
$PAGE->navbar->add( format_string($page->get_title()), new moodle_url('/mod/website/site.php', ['site' => $siteid, 'page' => $pageid]));
$PAGE->navbar->add(get_string('summary', 'report_reflectionexporter'));

// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/website/website.css', array('nocache' => rand())));

// Check view site permission.
if ( ! $site->can_user_view() ) {
    notice(get_string('nopermissiontoview', 'mod_website'), new moodle_url('/course/view.php', array('id' => $course->id)));
}


echo $OUTPUT->header();

$logs = utils::get_logs($siteid, $pageid, $course->id);



// Add scripts.
$PAGE->requires->js_call_amd('mod_website/logcontrol', 'init');


echo $OUTPUT->render_from_template('mod_website/site_logs', $logs);

echo $OUTPUT->footer();