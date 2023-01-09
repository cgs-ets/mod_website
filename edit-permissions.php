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
 * Edit a site.
 *
 * @package     mod_website
 * @copyright   2022 Michael Vangelovski <michael.vangelovski@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

use mod_website\utils;
use mod_website\forms\form_permissions;

// Course module id.
$type = required_param('type', PARAM_TEXT);
$siteid = required_param('site', PARAM_INT);
$pageid = required_param('page', PARAM_INT);
$embed = optional_param('embed', 0, PARAM_INT);

$site = new \mod_website\site($siteid);
$page = new \mod_website\page($pageid);

$cm = get_coursemodule_from_id('website', $site->get_cmid(), 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$website = $DB->get_record('website', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);
$thisurl = new moodle_url('/mod/website/edit-permissions.php', array(
    'type' => $type,
    'site' => $siteid,
    'page' => $pageid,
));
$gobackurl = new moodle_url('/mod/website/site.php', array(
    'site' => $siteid,
    'page' => $pageid,
));
$PAGE->set_url($thisurl);
$pagetitle = $website->name . ": " . get_string('permissions', 'mod_website');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_context($modulecontext);
$PAGE->navbar->add($website->name, $gobackurl);

// Wrap it in moodle.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/website/website.css', array('nocache' => rand())));
$PAGE->add_body_class('limitedwidth');

if ( ! $page->can_user_edit()) {
    notice(get_string('nopermissiontoedit', 'mod_website'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

$defaulteditors = array();
$additionaleditors = array();

// Get the site and page instanes.
$site = new \mod_website\site($siteid);
$site->load_editors();
$page = new \mod_website\page();
$page->read_for_site($siteid, $pageid);
$page->load_editors();

if ($type == 'site') 
{
    // Get current editors of the site.
    $defaulteditors = [$site->export_user()];
    $additionaleditors = $site->export_editors();
}
else if ($type == 'page') 
{
    // Get current editors of the page.
    // Default editors are (a) the site owner, (b) additional site editors.
    $defaulteditors = array_merge([$site->export_user()], $site->export_editors());
    $additionaleditors = $page->export_editors();
}

// Initialise the form.
$formsite = new form_permissions(
    $thisurl->out(false), 
    array(
        'embed' => $embed,
        'type' => $type,
        'websitedata' => $website,
        'defaulteditors' => $defaulteditors,
        'additionaleditors' => $additionaleditors,
    ), 
    'post', 
    '', 
    array('target' => '_parent', 'data-form' => 'website-permissions')
);

// Check if it is cancelled.
if ($formsite->is_cancelled()) {
    redirect($gobackurl->out());
    exit;
}

// Check if it is submitted.
$formdata = $formsite->get_data();
if (!empty($formdata)) {
    // Set up editing permission
    if (isset($formdata->editorstype)) {
        $formdata->courseid = $course->id;
        if ($type == 'site') 
        {
            $site->sync_permission_selections($formdata);
        }
        else if ($type == 'page') 
        {
            $page->sync_permission_selections($formdata);
        }
    }
    redirect($gobackurl->out());
    exit;
}

if ($embed) {
    $PAGE->add_body_classes(['fullscreen','embedded']);
}

echo $OUTPUT->header();

echo "<br>";
$formsite->display();

echo $OUTPUT->footer();
