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
$mode = optional_param('mode', '', PARAM_TEXT);

// Get the single site instance.
$site = new Site($siteid);

$cm = get_coursemodule_from_id('website', $site->get_cmid(), 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$website = $DB->get_record('website', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

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

if ( ! $site->can_user_view() ) {
    notice(get_string('nopermissiontoview', 'mod_website'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

// Check edit mode preference.
if ($mode != 'preview') { // Used for iframe preview in mod_form.
    $mode = 'view';
    if ($site->can_user_edit() && website_is_editing_on()) {
        $mode = 'edit';
    }
}

$site->fetch($pageid);
if (!$site->currentpage->get_id()) {
    $url->param('page', 0);
    redirect($url);
}

// Export the data. Also checks if user can edit.
$data = $site->export(array(
    'mode' => $mode == 'preview' ? 'view' : $mode,
    'course' => $course,
    'website' => $website,
    'modulecontext' => $modulecontext,
));

if ($mode == 'preview') {
    $data->canedit = false;
}

// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/website/website.css', array('nocache' => rand())));

// Add vendor code.
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/website/vendor/Sortable.min.js'), true );
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/website/vendor/dropzone/dropzone.min.js'), true );
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/website/static/css/dropzone.css'));


$PAGE->add_body_class('fullscreen');

// Add scripts.
$PAGE->requires->js_call_amd('mod_website/site', 'init');

// Wrap it in moodle.
echo $OUTPUT->header();

// Render the site. 
echo $OUTPUT->render_from_template('mod_website/site', $data);

if ($data->canedit) {
    $modal = array('id' => 'embeddedform', 'body' => '');
    echo $OUTPUT->render_from_template('mod_website/site_modal', $modal);
}

echo $OUTPUT->footer();
