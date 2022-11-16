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
        global $CFG, $PAGE;

        $update = optional_param('update', 0, PARAM_INT);

        $mform = $this->_form;
        $course_groups = groups_get_all_groups($PAGE->course->id);
        $course_grouping = groups_get_all_groupings($PAGE->course->id);

        $mform->addElement('hidden', 'update', $update);
        $mform->setType('update', PARAM_RAW);

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
            //'2' => 'Page for each student, editable and viewable by the student',
        );
        $select = $mform->addElement('select', 'distribution', get_string('distribution', 'mod_website'), $options);
        $select->setSelected('0');

        /************************
        * Groups
        *************************/
        $groups = array();
        if (!empty($course_groups)) {
            $groups['00_everyone'] = get_string('everyone', 'mod_website');
            $groups['0_group'] = get_string('groupsoptionheading', 'mod_website');
        }

        $count_empty = 0;
        foreach ($course_groups as $g) {
            // Skip empty groups.
            if (!groups_get_members($g->id, 'u.id')) {
                $count_empty++;
                continue;
            }
            $groups[$g->id . '_group'] = $g->name;
        }

        $not_show = false;
        if ($count_empty == count($course_groups)) {
            $not_show = true;
        }

        // Grouping.
        if (!empty($course_grouping)) {
            $groups['0_grouping'] = get_string('groupings', 'mod_website');
        }

        foreach ($course_grouping as $g) {
            // Only list those groupings with groups in it.
            if (empty(groups_get_grouping_members($g->id))) {
                continue;
            }
            $groups[$g->id . '_grouping'] = $g->name;
        }

        if (!empty($course_groups) && !$not_show) {
            $selectgroups = $mform->addElement('select', 'groups', get_string('groups', 'mod_website'), $groups, array('size' => 10));
            $mform->setDefault('groups', '00_everyone');
            $selectgroups->setMultiple(true);
            $mform->addHelpButton('groups', 'group_select', 'mod_website');
            $mform->hideIf('groups', 'distribution', 'eq', '0');
        }

        if ($update) {
            $mform->disabledIf('distribution', 'update', 'neq', '0'); 
            $mform->disabledIf('groups', 'update', 'neq', '0'); 
        }

        /************************
        * Availability
        *************************/
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

    /**
     * Validates forms elements.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (isset($data['groups']) && $this->group_validation($data)) {
            $errors['groups'] = get_string('std_invalid_selection', 'mod_website');
        }

        return $errors;
    }

    public function group_validation($data) {
        $everyone_group_grouping = in_array('00_everyone', $data['groups']) && count($data['groups']) > 1;
        return $everyone_group_grouping;
    }

    public function set_data($default_values) {

        $selectedgroups = array();        
        if (isset($default_values->groups)) {
            $selectedgroups = json_decode($default_values->groups);
        }
        if (empty($selectedgroups)) {
            $selectedgroups = array('00_everyone');
        }
        $default_values->groups = $selectedgroups;

        parent::set_data($default_values);
    }

}
