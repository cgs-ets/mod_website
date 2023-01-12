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
use mod_website\forms\form_sitemenu;

// Course module id.
$siteid = required_param('site', PARAM_INT);
$menuid = required_param('menu', PARAM_INT);
$pageid = optional_param('page', 0, PARAM_INT); // Go back to this page.
$embed = optional_param('embed', 0, PARAM_INT);

$site = new \mod_website\site($siteid);

$cm = get_coursemodule_from_id('website', $site->get_cmid(), 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$website = $DB->get_record('website', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);
$thisurl = new moodle_url('/mod/website/edit-menu.php', array(
    'site' => $siteid,
    'menu' => $menuid,
    'page' => $pageid,
));
$gobackurl = new moodle_url('/mod/website/site.php', array(
    'site' => $siteid,
    'page' => $pageid,
));

$PAGE->set_url($thisurl);
$pagetitle = $website->name . ": " . get_string('editmenu', 'mod_website');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_context($modulecontext);
$PAGE->navbar->add($website->name, $gobackurl);

// Wrap it in moodle.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/website/website.css', array('nocache' => rand())));
$PAGE->add_body_class('limitedwidth');

if ( ! $site->can_user_edit()) {
    notice(get_string('nopermissiontoedit', 'mod_website'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

// Initialise the form.
$formsitemenu = new form_sitemenu($thisurl->out(false), array(
    'menu' => array(
        'items' => $site->menu->export(array(
            'backend' => true,
        )),
        'classes' => 'list-move list-active'
    ),
    'unusedpages' => array(
        'items' => $site->menu->export(array(
            'pages' => $site->get_unused_pages(), 
            'backend' => true,
        )),
        'classes' => 'list-move list-inactive'
    ),
    'allpages' => array(
        'items' => $site->menu->export(array(
            'pages' => $site->get_all_pages(), 
            'backend' => true,
        )),
        'classes' => 'list-clone list-all'
    ),
    'embed' => $embed,
), 'post', '', array('target' => '_parent', 'data-form' => 'website-sitemenu'));

// Check if it is cancelled.
if ($formsitemenu->is_cancelled()) {
    redirect($gobackurl->out());
    exit;
}

// Check if it is submitted.
$formdata = $formsitemenu->get_data();
if (!empty($formdata)) {
    //echo "<pre>"; var_export($formdata->menujson); exit;
    // Save the menu.
    $menuarr = json_decode($formdata->menujson, true);
    $menu = new \mod_website\menu($menuid);
    $menu->update_menu_from_array($menuarr);
    redirect($gobackurl->out());
    exit;
}

// Set existing data.
$formsitemenu->set_data(array(
    'menujson' => $site->menu->get_json(),
));

// Add js.
$PAGE->requires->js( new moodle_url($CFG->wwwroot . '/mod/website/vendor/Sortable.min.js'), true );
$PAGE->requires->js_call_amd('mod_website/editmenu', 'init');

if ($embed) {
    $PAGE->add_body_classes(['fullscreen','embedded']);
}

echo $OUTPUT->header();

echo "<br>";
$formsitemenu->display();

echo $OUTPUT->footer();
