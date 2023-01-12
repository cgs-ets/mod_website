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
        global $CFG, $PAGE, $OUTPUT;

        // Add the javascript required to enhance this mform.
        $PAGE->requires->js_call_amd('mod_website/modform', 'init');
        $PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/website/website.css', array('nocache' => rand())));

        $update = optional_param('update', 0, PARAM_INT);

        $mform = $this->_form;

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
        $course_groups = groups_get_all_groups($PAGE->course->id);
        $course_grouping = groups_get_all_groupings($PAGE->course->id);
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

        $showgroups = true;
        if ($count_empty == count($course_groups)) {
            $showgroups = false;
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

        if (!empty($course_groups) && $showgroups) {
            $selectgroups = $mform->addElement('select', 'distgroups', get_string('groups', 'mod_website'), $groups, array('size' => 10, 'style' => 'width:100%;'));
            $mform->setDefault('distgroups', '00_everyone');
            $selectgroups->setMultiple(true);
            $mform->addHelpButton('distgroups', 'group_select', 'mod_website');
            $mform->hideIf('distgroups', 'distribution', 'eq', '0');
        }

        if ($update) {
            $mform->disabledIf('distribution', 'update', 'neq', '0'); 
            $mform->disabledIf('distgroups', 'update', 'neq', '0'); 
        }

        /************************
        * Template
        *************************/
        $mform->addElement('header', 'template', get_string('template', 'mod_website'));
        $mform->addElement('text', 'useexistingurl', get_string('useexistingurl', 'mod_website'), array('size' => '64'));
        $mform->setType('useexistingurl', PARAM_TEXT);
        $mform->addHelpButton('useexistingurl', 'useexistingurl', 'mod_website');
        $mform->addElement('html', $OUTPUT->render_from_template('mod_website/site_preview', array('siteurl' => '')));

        /************************
        * Availability
        *************************/
        $mform->addElement('header', 'availability', get_string('availability', 'assign'));

        $name = get_string('alloweditingfromdate', 'mod_website');
        $options = array('optional'=>true);
        $mform->addElement('date_time_selector', 'alloweditingfromdate', $name, $options);
        $mform->addHelpButton('alloweditingfromdate', 'alloweditingfromdate', 'mod_website');

        $name = get_string('cutoffdate', 'mod_website');
        $mform->addElement('date_time_selector', 'cutoffdate', $name, array('optional'=>true));
        $mform->addHelpButton('cutoffdate', 'cutoffdate', 'mod_website');


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

        if (isset($data['distgroups']) && $this->group_validation($data)) {
            $errors['distgroups'] = get_string('std_invalid_selection', 'mod_website');
        }

        return $errors;
    }

    public function group_validation($data) {
        $everyone_group_grouping = in_array('00_everyone', $data['distgroups']) && count($data['distgroups']) > 1;
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
