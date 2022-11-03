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
use mod_website\forms\form_sitepage;

// Course module id.
$siteid = required_param('site', PARAM_INT);
$pageid = optional_param('page', 0, PARAM_INT);

$site = new \mod_website\site($siteid);
if ( ! $site->can_user_edit()) {
    return;
}

$cm = get_coursemodule_from_id('website', $site->get_cmid(), 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$website = $DB->get_record('website', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);
$thisurl = new moodle_url('/mod/website/edit-page.php', array(
    'site' => $siteid,
    'page' => $pageid,
));
$gobackurl = new moodle_url('/mod/website/site.php', array(
    'site' => $siteid,
    'page' => $pageid,
));
$PAGE->set_url($thisurl);
$pagetitle = $website->name . ": " . get_string('editpage', 'mod_website');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_context($modulecontext);
$PAGE->navbar->add($website->name, $gobackurl);

// Wrap it in moodle.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/website/website.css', array('nocache' => rand())));
$PAGE->add_body_class('limitedwidth');

// Get the page.
$page = new \mod_website\page($pageid);

// Initialise the form.
$form = new form_sitepage($thisurl->out(false), array(), 'post', '', array('data-form' => 'website-sitepage'));

// Check if it is cancelled.
if ($form->is_cancelled()) {
    redirect($gobackurl->out());
    exit;
}

// Check if it is submitted.
$formdata = $form->get_data();
if (!empty($formdata)) {

    $formdata->siteid = $site->get_id();
    $page->save($formdata, $modulecontext);
    $gobackurl->param('page', $page->get_id());

    if ($formdata->addtomenu == 1) {
        $site->add_page_to_menu_top($page->get_id());
    }

    if ($formdata->addtomenu == 2) {        
        $gobackurl = new moodle_url('/mod/website/edit-menu.php', array(
            'site' => $siteid,
            'page' => $pageid,
            'menu' => $site->menu->get_id(),
        ));
    }

    redirect($gobackurl->out());
    exit;
}

// Load form.
// Set up filemanager.
$draftitemid = file_get_submitted_draft_itemid('bannerimage');
$fileoptions = form_sitepage::file_options();
file_prepare_draft_area($draftitemid, $modulecontext->id, 'mod_website', 'bannerimage', $page->get_id(), $fileoptions);

// Set the form values.
$form->set_data(array(
    'title' => $page->get_title(),
    'bannerimage' => $draftitemid,
));

echo $OUTPUT->header();

echo "<br>";
$form->display();

echo $OUTPUT->footer();
