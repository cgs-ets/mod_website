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
 * The main mod_website configuration form.
 *
 * @package     mod_website
 * @copyright   2022 Michael Vangelovski <michael.vangelovski@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form.
 *
 * @package     mod_website
 * @copyright   2022 Michael Vangelovski <michael.vangelovski@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_website_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('websitename', 'mod_website'), array('size' => '64'));

        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }

        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'websitename', 'mod_website');

        // Adding the standard "intro" and "introformat" fields.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        // Distribution
        $options = array(
            '0' => 'Single teacher-driven website, viewable by all students',
            '1' => 'Copy for each student, editable and viewable by the student',
        );
        $select = $mform->addElement('select', 'distribution', get_string('distribution', 'mod_website'), $options);
        $select->setSelected('0');
        $mform->addRule('distribution', null, 'required', null, 'client');

        
        // Availability
        $mform->addElement('header', 'availability', get_string('availability', 'assign'));
        $mform->setExpanded('availability', true);

        $name = get_string('alloweditingfromdate', 'mod_website');
        $options = array('optional'=>true);
        $mform->addElement('date_time_selector', 'alloweditingfromdate', $name, $options);
        $mform->addHelpButton('alloweditingfromdate', 'alloweditingfromdate', 'mod_website');

        $name = get_string('cutoffdate', 'mod_website');
        $mform->addElement('date_time_selector', 'cutoffdate', $name, array('optional'=>true));
        $mform->addHelpButton('cutoffdate', 'cutoffdate', 'mod_website');

        $mform->hideIf('availability', 'distribution', 'eq', '0');
        $mform->hideIf('alloweditingfromdate', 'distribution', 'eq', '0');
        $mform->hideIf('cutoffdate', 'distribution', 'eq', '0');


        // Add standard grading elements.
        $this->standard_grading_coursemodule_elements();

        // Add standard elements.
        $this->standard_coursemodule_elements();

        // Add standard buttons.
        $this->add_action_buttons();
    }
}
