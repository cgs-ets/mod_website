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
$siteid = required_param('site', PARAM_INT);
$pageid = optional_param('page', 0, PARAM_INT);

// Get the single site instance.
$site = new Site($siteid);

$cm = get_coursemodule_from_id('website', $site->get_cmid(), 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$website = $DB->get_record('website', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

if ( ! $site->can_user_view() ) {
    notice(get_string('nopermissiontoview', 'mod_website'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

// Check edit mode preference.
$mode = 'view';
if ($site->can_user_edit() && website_is_editing_on()) {
    $mode = 'edit';
}

$modulecontext = context_module::instance($cm->id);
$url = new moodle_url('/mod/website/site.php', array(
    'site' => $siteid,
    'page' => $pageid,
));
$website->url = $url->out(false);
$PAGE->set_url($url);
$PAGE->set_title(format_string($website->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/website/website.css', array('nocache' => rand())));
// Add vendor js.
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/website/js/Sortable.min.js'), true );

$PAGE->add_body_class('fullscreen');

// Wrap it in moodle.
echo $OUTPUT->header();

// Add scripts.
$PAGE->requires->js_call_amd('mod_website/site', 'init');

$site->fetch($pageid);

// Export the data. Also checks if this user is the site user (allowing editing)
$data = $site->export(array(
    'user' => $USER,
    'mode' => $mode,
    'course' => $course,
    'website' => $website,
    'modulecontext' => $modulecontext,
));

// Render the site. 
echo $OUTPUT->render_from_template('mod_website/site', $data);

if ($data->canedit) {

    $modal = array('id' => 'embeddedform', 'body' => '');
    echo $OUTPUT->render_from_template('mod_website/site_modal', $modal);

    /*// Prerender edit page modal.
    $modal = array('id' => 'modal-editpage', 'body' => '<iframe src="' . $data->embedded_editpageurl . '"></iframe>');
    echo $OUTPUT->render_from_template('mod_website/site_modal', $modal);

    // Prerender edit menu modal.
    $modal = array('id' => 'modal-editmenu', 'body' => '<iframe src="' . $data->embedded_editmenuurl . '"></iframe>');
    echo $OUTPUT->render_from_template('mod_website/site_modal', $modal);

    // Prerender new page modal.
    $modal = array('id' => 'modal-newpage', 'body' => '<iframe src="' . $data->embedded_newpageurl . '"></iframe>');
    echo $OUTPUT->render_from_template('mod_website/site_modal', $modal);

    // Prerender new section modal.
    $modal = array('id' => 'modal-newsection', 'body' => '<iframe src="' . $data->embedded_newsectionurl . '"></iframe>');
    echo $OUTPUT->render_from_template('mod_website/site_modal', $modal);*/
}

echo $OUTPUT->footer();
