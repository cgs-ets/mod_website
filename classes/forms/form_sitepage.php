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
 * Form definition for posting.
 * *
 * @package   mod_website
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_website\forms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');

class form_sitepage extends \moodleform {

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT, $USER, $DB;

        $mform =& $this->_form;
        $ishomepage = $this->_customdata['ishomepage'];
        $embed = $this->_customdata['embed'];
        $pageid = $this->_customdata['pageid'];
        $returnurl = $this->_customdata['returnurl'];

        /*----------------------
         *   Page title.
         *----------------------*/
        $mform->addElement('text', 'title', get_string('pagetitle', 'mod_website'), 'size="48"');
        $mform->setType('title', PARAM_TEXT);

        /*----------------------
        *   siteheader editor
        *----------------------*/
        $type = 'filemanager';
        $name = 'bannerimage';
        $title = get_string('bannerimage', 'mod_website');
        $mform->addElement($type, $name, $title, null, static::file_options());
        $mform->setType($name, PARAM_RAW);


        /*----------------------
        *   Visibility
        *----------------------*/
        if (!$ishomepage) {
            $options = array(
                0 => get_string('visible', 'mod_website'),
                1 => get_string('privatepage', 'mod_website'),
            );
            $select = $mform->addElement('select', 'visibility', get_string('visibility', 'mod_website'), $options);
            $select->setSelected(0);
        }

        /*----------------------
        *   Menu
        *----------------------*/
        $options = array(
            0 => 'No change',
            1 => 'Insert page at top (No change if page is already in the menu)',
        );
        $select = $mform->addElement('select', 'addtomenu', get_string('addtomenu', 'mod_website'), $options);
        $select->setSelected(0);

        /*----------------------
        *   Buttons
        *----------------------*/
        $this->add_action_buttons(!$embed);

        /*----------------------
        *   Delete
        *----------------------*/
        if ($pageid) {
            $mform->addElement('html', '<a data-pageid="' . $pageid . '" data-returnurl="' . $returnurl . '" class="btn-delete btn btn-danger float-right">Delete page</a>');
        }

        /*----------------------
        *   Hidden
        *----------------------*/
        $mform->addElement('hidden', 'sections');
        $mform->setType('sections', PARAM_TEXT);
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
            'accepted_types' => array('image'),
            'return_types' => FILE_INTERNAL | FILE_CONTROLLED_LINK
        );
    }

}