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
 * Form definition for block.
 * *
 * @package   mod_website
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_website\forms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');

class form_siteblock extends \moodleform {

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT, $USER, $DB;

        $mform =& $this->_form;

        /*----------------------
        *   Type of block
        *----------------------*/
        $typearray=array();
        $typearray[] = $mform->createElement('radio', 'type', '', get_string('wysiwyg', 'mod_website'), 'editor');
        $typearray[] = $mform->createElement('radio', 'type', '', get_string('picturebutton', 'mod_website'), 'picturebutton');
        $typearray[] = $mform->createElement('html', '<hr style="margin-top: 30px;">');
        $mform->addGroup($typearray, 'typearray', get_string('blocktype', 'mod_website'), array(' '), false);
        $mform->setDefault('type', 'editor');


        /*----------------------
        *   Content editor
        *----------------------*/
        $group = array();
        $title = get_string('content', 'mod_website');
        $group[] =& $mform->createElement('editor', 'content', '', null, static::editor_options());
        $mform->addGroup($group, 'editorgroup', '', array(''), false);
        $mform->hideIf('editorgroup', 'type', 'neq', 'editor');


        /*----------------------
        *   Picture button
        *----------------------*/
        $group = array();
        $group[] =& $mform->createElement('text', 'buttontitle', get_string('buttontitle', 'mod_website'), 'size="48"'); //Caption
        $group[] =& $mform->createElement('text', 'buttonurl', get_string('buttonurl', 'mod_website'), 'size="48"'); //URL
        $group[] =& $mform->createElement('filemanager', 'buttonpicture', '', null, static::picture_options()); //Image

        $mform->addGroup($group, 'picturebuttongroup', '', array(''), false);
        $mform->hideIf('picturebuttongroup', 'type', 'neq', 'picturebutton');

        /*----------------------
        *   Visibility
        *----------------------*/
        $options = array(
            0 => get_string('visible', 'mod_website'),
            1 => get_string('privateblock', 'mod_website'),
        );
        $select = $mform->addElement('select', 'visibility', get_string('visibility', 'mod_website'), $options);
        $select->setSelected(0);
        $mform->addRule('visibility', null, 'required', null, 'client');

        
        /*----------------------
        *   Buttons
        *----------------------*/
        $this->add_action_buttons();

    }

    /**
     * Returns editor options
     *
     * @return array
     */
    public static function editor_options() {
        global $CFG;

        return array(
            'maxbytes' => $CFG->maxbytes,
            'maxfiles' => 50,
            'trusttext'=> true,
            'noclean' => true,
            'return_types'=> FILE_INTERNAL | FILE_EXTERNAL,
            'subdirs' => 0,
        );
    }

    /**
     * Returns the options array to use in editor
     *
     * @return array
     */
    public static function picture_options() {
        global $CFG;

        return array(
            'maxfiles' => 1,
            'subdirs' => 0,
            'maxbytes' => $CFG->maxbytes,
            'accepted_types' => array('jpeg','jpg','png'),
            'return_types' => FILE_INTERNAL | FILE_CONTROLLED_LINK
        );
    }

}