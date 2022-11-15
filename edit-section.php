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
use mod_website\forms\form_sitesection;

// Course module id.
$siteid = required_param('site', PARAM_INT);
$pageid = required_param('page', PARAM_INT);
$sectionid = optional_param('section', 0, PARAM_INT);

$site = new \mod_website\site($siteid);

$cm = get_coursemodule_from_id('website', $site->get_cmid(), 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$website = $DB->get_record('website', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

if ( ! $site->can_user_edit()) {
    notice(get_string('nopermissiontoedit', 'mod_website'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

$modulecontext = context_module::instance($cm->id);
$thisurl = new moodle_url('/mod/website/edit-section.php', array(
    'site' => $siteid,
    'page' => $pageid,
    'section' => $sectionid,
));
$gobackurl = new moodle_url('/mod/website/site.php', array(
    'site' => $siteid,
    'page' => $pageid,
));
$PAGE->set_url($thisurl);
$pagetitle = $website->name . ": " . get_string('editsection', 'mod_website');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_context($modulecontext);
$PAGE->navbar->add($website->name, $gobackurl);

// Wrap it in moodle.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/website/website.css', array('nocache' => rand())));
$PAGE->add_body_class('limitedwidth');

// Initialise the form.
$formsitesection = new form_sitesection($thisurl->out(false), 
    array(
        'sectionid' => $sectionid,
        'returnurl' => $gobackurl->out(),
    ), 'post', '', array('data-form' => 'website-sitesection'));

// Check if it is cancelled.
if ($formsitesection->is_cancelled()) {
    redirect($gobackurl->out());
    exit;
}

// Check if it is submitted.
$formdata = $formsitesection->get_data();
if (!empty($formdata)) {
    // Save the section record.
    $formdata->id = $sectionid;
    $formdata->siteid = $site->get_id();
    $formdata->sectionoptions = json_encode( (object) array(
        'hidetitle' => $formdata->hidetitle,
        'collapsible' => $formdata->collapsible,
    ));
    $formdata->title = $formdata->sectiontitle;
    $formdata->hidden = $formdata->visibility;
    $section = new \mod_website\section();
    $sectionid = $section->save($formdata);
    // Add the section to the page.
    if ($pageid > 0) {
        $page = new \mod_website\page($pageid);
        $page->add_section_to_page($sectionid);  
    }
    redirect($gobackurl->out());
    exit;
}

// Initialise the form values.
if ($sectionid) {
    $section = $site->get_section($sectionid);
    $options = json_decode($section->sectionoptions);
    $formsitesection->set_data(array(
        'sectiontitle' => $section->title,
        'layout' => $section->layout,
        'blocks' => $section->blocks,
        'hidetitle' => $options->hidetitle,
        'collapsible' => $options->collapsible,
        'visibility' => $section->hidden,
    ));
}

$PAGE->requires->js_call_amd('mod_website/editsection', 'init');
echo $OUTPUT->header();

echo "<br>";
$formsitesection->display();

echo $OUTPUT->footer();
