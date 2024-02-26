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
 * Get the content for a block.
 *
 * @package     mod_website
 * @copyright   2022 Michael Vangelovski <michael.vangelovski@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');


// Course module id.
$siteid = required_param('site', PARAM_INT);
$sectionid = required_param('section', PARAM_INT);
$blockid = optional_param('block', 0, PARAM_INT);
$pageid = optional_param('page', 0, PARAM_INT);
$embed = optional_param('embed', 1, PARAM_INT);

$site = new \mod_website\site($siteid);
$page = new \mod_website\page($pageid);  
$block = new \mod_website\block($blockid);  


$cm = get_coursemodule_from_id('website', $site->get_cmid(), 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$website = $DB->get_record('website', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);
$thisurl = new moodle_url('/mod/website/block.php', array(
    'site' => $siteid,
    'page' => $pageid,
    'section' => $sectionid,
    'block' => $blockid,
));

$PAGE->set_url($thisurl);
$pagetitle = $website->name . ": " . get_string('editblock', 'mod_website');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_context($modulecontext);

// Wrap it in moodle.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/website/website.css', array('nocache' => rand())));
$PAGE->add_body_class('limitedwidth');

if ($block->get_siteid() != $siteid) {
    // Something is terribly wrong.
    exit;
}
if ( ! $page->can_user_view()) {
    notice(get_string('nopermissiontoview', 'mod_website'), new moodle_url('/course/view.php', array('id' => $course->id)));
}
if ( ! $page->has_section($sectionid)) {
    notice(get_string('nopermissiontoview', 'mod_website'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

$exported = $block->export(array(
    'siteid' => $siteid,
    'pageid' => $pageid,
    'sectionid' => $sectionid,
    'modulecontext' => $modulecontext,
    'website' => $website,
    'rawcontent' => true, // This forces it to export the content, not merely button construct with contenturl
));

if ($embed) {
    $PAGE->add_body_classes(['fullscreen','embedded']);
}

// Add css.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/website/website.css', array('nocache' => rand())));

// Add scripts.
//$PAGE->requires->js_call_amd('mod_website/site', 'init');

// Wrap it in moodle.
echo $OUTPUT->header();

// Render the site. 
//echo $OUTPUT->render_from_template('mod_website/site', $data);
echo $exported['html'];

echo $OUTPUT->footer();