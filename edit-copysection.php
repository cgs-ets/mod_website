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
 * Copy a section
 *
 * @package     mod_website
 * @copyright   2022 Michael Vangelovski <michael.vangelovski@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

use mod_website\utils;
use mod_website\copying;
use mod_website\forms\form_sectioncopy;

// Course module id.
$siteid = required_param('site', PARAM_INT);
$pageid = required_param('page', PARAM_INT);
$sectionid = required_param('section', PARAM_INT);
$embed = optional_param('embed', 0, PARAM_INT);

$site = new \mod_website\site($siteid);
$page = new \mod_website\page($pageid);

$cm = get_coursemodule_from_id('website', $site->get_cmid(), 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$website = $DB->get_record('website', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);
$thisurl = new moodle_url('/mod/website/edit-copysection.php', array(
    'site' => $siteid,
    'page' => $pageid,
    'section' => $sectionid,
));
$gobackurl = new moodle_url('/mod/website/site.php', array(
    'site' => $siteid,
    'page' => $pageid,
));
$PAGE->set_url($thisurl);
$pagetitle = $website->name . ": " . get_string('copysection', 'mod_website');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_context($modulecontext);
$PAGE->navbar->add($website->name, $gobackurl);

// Wrap it in moodle.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/website/website.css', array('nocache' => rand())));
$PAGE->add_body_class('limitedwidth');

// Make sure user can edit this page.
if ( ! $page->can_user_edit()) {
    notice(get_string('nopermissiontoedit', 'mod_website'), new moodle_url('/course/view.php', array('id' => $course->id)));
}


$sitepages = $site->get_all_pages();

// Initialise the form.
$form = new form_sectioncopy(
    $thisurl->out(false), 
    array(
        'embed' => $embed,
        'sitepages' => $sitepages,
    ), 
    'post', 
    '', 
    array('target' => '_parent', 'data-form' => 'website-sectioncopy')
);

// Check if it is cancelled.
if ($form->is_cancelled()) {
    redirect($gobackurl->out());
    exit;
}

// Check if it is submitted.
$formdata = $form->get_data();
if (!empty($formdata)) {
    if (empty($formdata->copytopage)) {
        $formdata->copytopage = $page->get_id();
    }
    // Perform the copy.
    copying::clone_section_into_page($site->get_id(), $sectionid, $formdata->copytopage);
    // Redirect back to copytopage.
    $nextpage = new moodle_url('/mod/website/site.php', array(
        'site' => $siteid,
        'page' => $formdata->copytopage,
    ));
    redirect($nextpage->out());
    exit;
}

if ($embed) {
    $PAGE->add_body_classes(['fullscreen','embedded']);
}

echo $OUTPUT->header();

echo "<br>";
$form->display();

echo $OUTPUT->footer();
