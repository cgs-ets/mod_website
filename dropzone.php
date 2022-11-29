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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This script receives uploades from the current user
 *
 * @package   mod_website
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

// disable moodle specific debug messages and any errors in output
define('NO_DEBUG_DISPLAY', true);

// Include required files and classes.
require_once('../../config.php');

require_login();
if (isguestuser()) {
    print_error('noguest');
}
require_sesskey();

$siteid = required_param('site', PARAM_INT);
$sectionid = required_param('section', PARAM_INT);
$upload = optional_param('upload', 0, PARAM_INT);
$remove = optional_param('remove', 0, PARAM_INT);
$fileid = optional_param('fileid', '', PARAM_RAW);

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir .'/filestorage/file_system_filedir.php');
require_once($CFG->libdir .'/filestorage/stored_file.php');

$tempdir = str_replace('\\\\', '\\', $CFG->dataroot) . '\mod_website\uploads\\';

if ($upload) {
    // Handle the file upload.
    $file = $_FILES['file'];
    //var_export($_FILES); exit;

    $path = $file['tmp_name']; // temporary upload path of the file.
    $filename = date('YmdHis', time()) . '_' . $USER->id . '_' . $file['name']; // desired name of the file.
    $filename = str_replace(' ', '-', $filename); // Replaces all spaces with hyphens.
    $filename = preg_replace('/[^A-Za-z0-9\_\-\.]/', '', $filename); // Removes special chars, leaving only letters numbers, underscore, dash and dot.
    //var_export($name); exit;

    // Check for the temp dir before moving forward.
    $temprootdir = str_replace('\\\\', '\\', $CFG->dataroot) . '\mod_website\\';
    if (!is_dir($temprootdir)) {
        if (!mkdir($temprootdir)) {
            return array('code' => 'failed', 'data' => 'Failed to create mod_website dir: ' . $temprootdir);
        }
    }
    if (!is_dir($tempdir)) {
        if (!mkdir($tempdir)) {
            return array('code' => 'failed', 'data' => 'Failed to create uploads dir: ' . $tempdir);
        }
    }

    // Move to temp dir.
    $result = move_uploaded_file($path, $tempdir . $filename);

    /**********************
     * CREATE THE BLOCK!
     **********************/
    $site = new \mod_website\site($siteid);
    if ( ! $site->get_id()) {
        return;
    }
    $modulecontext = context_module::instance($site->get_cmid());

    // Store the file to the permanent store.
    $fsfd = new \file_system_filedir();
    $fs = new \file_storage();
    $filepath = $tempdir . $filename;
    $newrecord = new \stdClass();
    // Copy the temp file into moodledata.
    list($newrecord->contenthash, $newrecord->filesize, $newfile) = $fsfd->add_file_from_path($filepath);
    // Start setting up the file db record.
    $newrecord->contextid = $modulecontext->id;
    $newrecord->component = 'mod_website';
    $newrecord->filepath  = '/';
    $newrecord->filename  = $filename;
    $newrecord->timecreated  = time();
    $newrecord->timemodified = time();
    $newrecord->userid      = $USER->id;
    $newrecord->source      = $filename;
    $newrecord->author      = fullname($USER);
    $newrecord->license     = $CFG->sitedefaultlicense;
    $newrecord->status      = 0;
    $newrecord->sortorder   = 0;
    $newrecord->mimetype    = $fs->get_file_system()->mimetype_from_hash($newrecord->contenthash, $newrecord->filename);
    
    $cleanname = substr($file['name'], 0, strrpos($file['name'], '.'));
    $isimage = strpos($newrecord->mimetype, 'image') !== false ? 1 : 0;
    $isvideo = strpos($newrecord->mimetype, 'video') !== false ? 1 : 0;
    $isother = !$isimage && !$isvideo;

    $block = new \mod_website\block();

    if ( $isimage || $isvideo ) 
    {
        // Add into a normal content block.
        $html = '<img src="@@PLUGINFILE@@/' . $filename . '" role="presentation">';
        if ($isvideo) {
            $html = '<video controls><source src="@@PLUGINFILE@@/' . $filename . '" type="' . $mime . '">Your browser does not support the video tag.</video>';
        }
        $blockdata = array(
            'siteid' => $site->get_id(),
            'type' => 'editor',
            'content' => $html,
        );

        // Create the block with data.
        $block->create($blockdata);

        $newrecord->filearea  = 'content';
        $newrecord->itemid    = $block->get_id();
        $newrecord->pathnamehash = $fs->get_pathname_hash($newrecord->contextid, $newrecord->component, $newrecord->filearea, $newrecord->itemid, $newrecord->filepath, $newrecord->filename);
        $DB->insert_record('files', $newrecord);
    } 
    else if ($isother) 
    {
        // Create a file button.
        $blockdata = array(
            'siteid' => $site->get_id(),
            'type' => 'picturebutton',
            'content' => json_encode(array(
                'buttontitle' => $cleanname,
                'linktype' => "file",
                'buttonurl' => '',
                'includepicture' => 0,
            )),
        );

        // Create the block with data.
        $block->create($blockdata);

        $newrecord->filearea  = 'buttonfile';
        $newrecord->itemid    = $block->get_id();
        $newrecord->pathnamehash = $fs->get_pathname_hash($newrecord->contextid, $newrecord->component, $newrecord->filearea, $newrecord->itemid, $newrecord->filepath, $newrecord->filename);
        $DB->insert_record('files', $newrecord);
    }

    // Add the directory rec...
    $dirrec = clone($newrecord);
    $dirrec->filename  = '.';
    $dirrec->filesize  = 0;
    $dirrec->mimetype = null;
    $dirrec->source = null;
    $dirrec->author = null;
    $dirrec->pathnamehash = $fs->get_pathname_hash($dirrec->contextid, $dirrec->component, $dirrec->filearea, $dirrec->itemid, $dirrec->filepath, $dirrec->filename);
    $DB->insert_record('files', $dirrec);

    /*
    // PREVIEW
    if ($isimage) {
        // Copy it to the file store a second time.
        //list($newrecord->contenthash, $newrecord->filesize, $newfile) = $fsfd->add_file_from_path($filepath);
        // Add the db rec.
        $newrecord->filearea  = 'picturebutton';
        $newrecord->pathnamehash = $fs->get_pathname_hash($newrecord->contextid, $newrecord->component, $newrecord->filearea, $newrecord->itemid, $newrecord->filepath, $newrecord->filename);
        $DB->insert_record('files', $newrecord);
        // Add the directory rec...
        $dirrec->filearea  = 'picturebutton';
        $dirrec->pathnamehash = $fs->get_pathname_hash($dirrec->contextid, $dirrec->component, $dirrec->filearea, $dirrec->itemid, $dirrec->filepath, $dirrec->filename);
        $DB->insert_record('files', $dirrec);
    }
    */

    // Add the block to the section.
    $section = new \mod_website\section($sectionid);
    $section->add_block_to_section($block->get_id());  

    // Remove the temp file.
    unlink($filepath);

    return;

} else if ($remove) {
    $result = unlink($tempdir . $fileid);
    return;
}