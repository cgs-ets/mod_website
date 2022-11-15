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

class form_sitesection extends \moodleform {

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT, $USER, $DB;

        $mform =& $this->_form;

        /*----------------------
         *   Section title.
         *----------------------*/
        $mform->addElement('text', 'sectiontitle', get_string('sectiontitle', 'mod_website'), 'size="48"');
        $mform->setType('sectiontitle', PARAM_TEXT);


        $mform->addElement('advcheckbox', 'hidetitle', get_string('hidetitle', 'mod_website'), ' ', array(), array(0, 1));
        $mform->addElement('advcheckbox', 'collapsible', get_string('collapsible', 'mod_website'), ' ', array(), array(0, 1));
        $mform->hideIf('collapsible', 'hidetitle', 'eq', 1);


        /*----------------------
         *   Section layout.
         *----------------------*/
        $layoutarray=array();
        
        // Responsive grid.
        $layoutarray[] = $mform->createElement('radio', 'layout', '', 'Responsive grid <div style="margin-bottom: 5px;width: 100%;"><img src="/mod/website/pix/website-layout-4.png" /></div>', 4, array('class' => 'w-100'));
        
        // Horizontal.
        $layoutarray[] = $mform->createElement('radio', 'layout', '', 'Horizontally divided <div style="margin-bottom: 5px;width: 100%;"><img src="/mod/website/pix/website-layout-1.png" /></div>', 1, array('class' => 'w-100'));
        
        // Half, right side vertical.
        $layoutarray[] = $mform->createElement('radio', 'layout', '', 'Left half fixed, right half vertical <div style="margin-bottom: 5px;width: 100%;"><img src="/mod/website/pix/website-layout-2.png" /></div>', 2, array('class' => 'w-100'));
        
        // Half, left side vertical.
        $layoutarray[] = $mform->createElement('radio', 'layout', '', 'Right half fixed, left half vertical <div style="margin-bottom: 5px;width: 100%;"><img src="/mod/website/pix/website-layout-3.png" /></div>', 3, array('class' => 'w-100'));

        $mform->addGroup($layoutarray, 'layoutar', get_string('sectionlayout', 'mod_website'), array(' '), false);

        /*----------------------
        *   Visibility
        *----------------------*/
        $options = array(
            0 => get_string('visible', 'mod_website'),
            1 => get_string('privatesection', 'mod_website'),
        );
        $select = $mform->addElement('select', 'visibility', get_string('visibility', 'mod_website'), $options);
        $select->setSelected(0);
        $mform->addRule('visibility', null, 'required', null, 'client');

        /*----------------------
        *   Buttons
        *----------------------*/
        $this->add_action_buttons();

        /*----------------------
        *   Hidden
        *----------------------*/
        $mform->addElement('hidden', 'blocks');
        $mform->setType('blocks', PARAM_TEXT);
    }

}