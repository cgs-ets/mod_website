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
 * Edit a block.
 *
 * @package     mod_website
 * @copyright   2022 Michael Vangelovski <michael.vangelovski@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

use mod_website\utils;
use mod_website\forms\form_siteblock;

// Course module id.
$siteid = required_param('site', PARAM_INT);
$sectionid = required_param('section', PARAM_INT);
$blockid = optional_param('block', 0, PARAM_INT);
$pageid = optional_param('page', 0, PARAM_INT);
$embed = optional_param('embed', 0, PARAM_INT);

$site = new \mod_website\site($siteid);

$cm = get_coursemodule_from_id('website', $site->get_cmid(), 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$website = $DB->get_record('website', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

if ( ! $site->can_user_edit()) {
    notice(get_string('nopermissiontoedit', 'mod_website'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

$modulecontext = context_module::instance($cm->id);
$thisurl = new moodle_url('/mod/website/edit-block.php', array(
    'site' => $siteid,
    'page' => $pageid,
    'section' => $sectionid,
    'block' => $blockid,
));
$gobackurl = new moodle_url('/mod/website/site.php', array(
    'site' => $siteid,
    'page' => $pageid,
));

$PAGE->set_url($thisurl);
$pagetitle = $website->name . ": " . get_string('editblock', 'mod_website');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_context($modulecontext);
$PAGE->navbar->add($website->name, $gobackurl);

// Wrap it in moodle.
$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/website/website.css', array('nocache' => rand())));
$PAGE->add_body_class('limitedwidth');

// Initialise the form.
$formsiteblock = new form_siteblock(
    $thisurl->out(false), 
    array(
        'blockid' => $blockid,
        'returnurl' => $gobackurl->out(),
        'embed' => $embed,
    ), 
    'post', 
    '', 
    array('target' => '_top', 'data-form' => 'website-siteblock')
);

// Check if it is cancelled.
if ($formsiteblock->is_cancelled()) {
    redirect($gobackurl->out());
    exit;
}

// Check if it is submitted.
$formdata = $formsiteblock->get_data();
if (!empty($formdata)) {
    // Save the block record.
    $formdata->id = $blockid;
    $formdata->siteid = $site->get_id();
    $formdata->hidden = $formdata->visibility;
    $block = new \mod_website\block();
    $blockid = $block->save($formdata, $modulecontext);

    // Add the block to the section.
    if ($sectionid > 0) {
        $section = new \mod_website\section($sectionid);
        $section->add_block_to_section($blockid);  
    }
    redirect($gobackurl->out());
    exit;
}

// If existing, initialise the form values.
if ($blockid) {
    $block = $site->get_block($blockid);

    if ($block->type == 'editor' || empty($block->type)) {
        // Set up editor.
        $draftideditor = file_get_submitted_draft_itemid('content');
        $editoroptions = form_siteblock::editor_options();
        $contenttext = file_prepare_draft_area($draftideditor, $modulecontext->id, 'mod_website', 'content', $blockid, $editoroptions, $block->content);
        $content = array(
            'text' => $contenttext,
            'format' => editors_get_preferred_format(),
            'itemid' => $draftideditor
        );
        $formsiteblock->set_data(array(
            'type' => 'editor',
            'content' => $content,
            'visibility' => $block->hidden,
        ));
    }

    if ($block->type == 'picturebutton') {
        $settings = json_decode($block->content);

        // Button file filemanager.
        $draftfileitemid = file_get_submitted_draft_itemid('buttonfile');
        file_prepare_draft_area($draftfileitemid, $modulecontext->id, 'mod_website', 'buttonfile', $blockid, form_siteblock::file_options());

        // Button image filemanager.
        $draftpictureitemid = file_get_submitted_draft_itemid('picturebutton');
        file_prepare_draft_area($draftpictureitemid, $modulecontext->id, 'mod_website', 'picturebutton', $blockid, form_siteblock::picture_options());
        
        $formsiteblock->set_data(array(
            'type' => $block->type,
            'buttontitle' => $settings->buttontitle,
            'buttonlinktypegroup[buttonlinktype]' => $settings->linktype,
            'buttonurl' => $settings->buttonurl,
            'buttonfile' => $draftfileitemid,
            'includepicturegroup[includepicture]' => $settings->includepicture,
            'buttonpicture' => $draftpictureitemid,
        ));
    }
    
    
}
$PAGE->requires->js_call_amd('mod_website/editblock', 'init');

if ($embed) {
    $PAGE->add_body_classes(['fullscreen','embedded']);
}

echo $OUTPUT->header();

echo "<br>";
$formsiteblock->display();

echo $OUTPUT->footer();
