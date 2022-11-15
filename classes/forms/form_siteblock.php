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
        $blockid = $this->_customdata['blockid'];
        $returnurl = $this->_customdata['returnurl'];

        /*----------------------
        *   Type of block
        *----------------------*/
        $typearray=array();
        $typearray[] = $mform->createElement('radio', 'type', '', get_string('wysiwyg', 'mod_website') . '&nbsp&nbsp;', 'editor', array('class' => 'blocktype'));
        $typearray[] = $mform->createElement('radio', 'type', '', get_string('button', 'mod_website'), 'picturebutton', array('class' => 'blocktype'));
        $mform->addGroup($typearray, 'typearray', get_string('blocktype', 'mod_website'), array(' '), false);
        $mform->setDefault('type', 'editor');
        $mform->addElement('html', '<hr style="margin: 25px 0;">');



        /*----------------------
        *   Content editor
        *----------------------*/
        $mform->addElement('editor', 'content', '', null, static::editor_options());

        /*----------------------
        *   Picture button
        *----------------------*/
        // Button caption
        $mform->addElement('text', 'buttontitle', get_string('buttontitle', 'mod_website'), 'size="48"'); 
        $mform->setType('buttontitle', PARAM_TEXT);

        // What are you linking to?
        $buttonlinktype=array();
        $buttonlinktype[] = $mform->createElement('radio', 'buttonlinktype', null, get_string('buttonfile', 'mod_website'), 'file', array('class' => 'linktype'));
        $buttonlinktype[] = $mform->createElement('radio', 'buttonlinktype', null, get_string('buttonurl', 'mod_website'), 'url', array('class' => 'linktype'));
        $mform->addGroup($buttonlinktype, 'buttonlinktypegroup', get_string('buttonlinktype', 'mod_website'));

        // URL
        $mform->addElement('text', 'buttonurl', get_string('buttonurl', 'mod_website'), 'size="48"');
        $mform->setType('buttonurl', PARAM_TEXT);

        // Link to file
        $mform->addElement('filemanager', 'buttonfile', get_string('uploadfile', 'mod_website'), get_string('buttonfile', 'mod_website'), static::file_options()); 

        // Would you like a button photo?
        $includepicture=array();
        $includepicture[] = $mform->createElement('radio', 'includepicture', null, 'Yes', 1, array('class' => 'includepicture'));
        $includepicture[] = $mform->createElement('radio', 'includepicture', null, 'No', 0, array('class' => 'includepicture'));
        $mform->addGroup($includepicture, 'includepicturegroup', get_string('includepicture', 'mod_website'));

        // Button photo
        $mform->addElement('filemanager', 'buttonpicture', get_string('buttonpicture', 'mod_website'), get_string('buttonpicture', 'mod_website'), static::picture_options());
      
        
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
        
        if ($blockid) {
            $mform->addElement('html', '<a data-blockid="' . $blockid . '" data-returnurl="' . $returnurl . '" class="btn-delete   btn btn-danger float-right">Delete block</a>');
        }

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

    /**
     * Returns the options array to use in editor
     *
     * @return array
     */
    public static function file_options() {
        global $CFG;

        return array(
            'maxfiles' => 1,
            'subdirs' => 0,
            'maxbytes' => $CFG->maxbytes,
            'return_types' => FILE_INTERNAL | FILE_CONTROLLED_LINK
        );
    }

}