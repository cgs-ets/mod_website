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

class form_permissions extends \moodleform {

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT, $USER, $DB, $PAGE;

        $mform =& $this->_form;
        $websitedata = $this->_customdata['websitedata'];
        $embed = $this->_customdata['embed'];
        $type = $this->_customdata['type'];
        $defaulteditors = $this->_customdata['defaulteditors'];
        $additionaleditors = $this->_customdata['additionaleditors'];
       
        /************************
        * Sharing/Permissions
        *************************/
        // Edit permissions title
        $mform->addElement('html', '<h3>' . get_string('editpermissions', 'mod_website', $type) . '</h3>');

        // Default editors blurb
        if ($websitedata->distribution == 1) {
            $mform->addElement('html', get_string('distmultisharing', 'mod_website'));
        }
        else if ($websitedata->distribution == 2) {
            $mform->addElement('html', get_string('distpagesharing', 'mod_website'));
            // Current editors
            $mform->addElement('html', $OUTPUT->render_from_template('mod_website/site_settings_editors', array(
                'defaulteditors' => $defaulteditors,
                'additionaleditors' => $additionaleditors,
            )));
        }
        else if ($websitedata->distribution == 0) {

            if ($type == 'site') {
                $mform->addElement('html', get_string('sitepermissionsblurb', 'mod_website'));
            } else if ($type == 'page') {
                $mform->addElement('html', get_string('pagepermissionsblurb', 'mod_website'));
            }
        
            // Current editors
            $mform->addElement('html', $OUTPUT->render_from_template('mod_website/site_settings_editors', array(
                'defaulteditors' => $defaulteditors,
                'additionaleditors' => $additionaleditors,
            )));
            $mform->addElement('html', '<h4>Modify editors</h4>');

            /* Editers defined by */
            $typearray=array();
            $typearray[] = $mform->createElement('radio', 'editorstype', '', get_string('nochange', 'mod_website'), 'nochange');
            $typearray[] = $mform->createElement('radio', 'editorstype', '', get_string('groups', 'mod_website'), 'groups');
            $typearray[] = $mform->createElement('radio', 'editorstype', '', get_string('roles', 'mod_website'), 'roles');
            $typearray[] = $mform->createElement('radio', 'editorstype', '', get_string('users', 'mod_website'), 'users');
            $typearray[] = $mform->createElement('radio', 'editorstype', '', get_string('removeall', 'mod_website'), 'removeall');
            $mform->addGroup($typearray, 'editorstypearray', '', array(' '), false);
            $mform->setDefault('editorstype', 'nochange');
            $mform->hideIf('editorstypearray', 'distribution', 'neq', '0');

            /* Groups */
            $course_groups = groups_get_all_groups($PAGE->course->id);
            $course_grouping = groups_get_all_groupings($PAGE->course->id);
            $sharegroups = array();
            $count_empty = 0;
            foreach ($course_groups as $g) {
                // Skip empty groups.
                if (!groups_get_members($g->id, 'u.id')) {
                    $count_empty++;
                    continue;
                }
                $sharegroups[$g->id . '_group'] = $g->name;
            }

            $showgroups = true;
            if ($count_empty == count($course_groups)) {
                $showgroups = false;
            }

            // Grouping.
            if (!empty($course_grouping)) {
                $sharegroups['0_grouping'] = get_string('groupings', 'mod_website');
            }

            foreach ($course_grouping as $g) {
                // Only list those groupings with groups in it.
                if (empty(groups_get_grouping_members($g->id))) {
                    continue;
                }
                $sharegroups[$g->id . '_grouping'] = $g->name;
            }
            if (!empty($sharegroups)) {
                $select = $mform->addElement('select', 'sharinggroups', get_string('groups', 'mod_website'), $sharegroups, array('size' => 10, 'style' => 'width:100%;'));
                $select->setMultiple(true);
                $mform->addHelpButton('sharinggroups', 'group_select', 'mod_website');
                $mform->hideIf('sharinggroups', 'distribution', 'neq', '0');
                $mform->hideIf('sharinggroups', 'editorstype', 'neq', 'groups');
            }

            /* Roles */
            $roles = array();
            $course_roles = get_roles_used_in_context(\context_course::instance($PAGE->course->id), false);
            foreach ($course_roles as $r) {
                $roles[$r->id . '_role'] = $r->shortname;
            }
            if (!empty($roles)) {
                $select = $mform->addElement('select', 'sharingroles', get_string('roles', 'mod_website'), $roles, array('size' => 10, 'style' => 'width:100%;'));
                $select->setMultiple(true);
                $mform->hideIf('sharingroles', 'distribution', 'neq', '0');
                $mform->hideIf('sharingroles', 'editorstype', 'neq', 'roles');
            }

            /* Users */
            $users = array();
            foreach ($course_roles as $role) {
                $users[$role->id . '_roleheading'] = '---- ' . $role->shortname . ' ----';
                $roleusers = get_role_users($role->id, \context_course::instance($PAGE->course->id));
                foreach ($roleusers as $u) {
                    $users[$u->id . '_user'] = $u->lastname . ', ' . $u->firstname;
                }
            }
            if (!empty($users)) {
                $select = $mform->addElement('select', 'sharingusers', get_string('users', 'mod_website'), $users, array('size' => 10, 'style' => 'width:100%;'));
                $select->setMultiple(true);
                $mform->hideIf('sharingusers', 'distribution', 'neq', '0');
                $mform->hideIf('sharingusers', 'editorstype', 'neq', 'users');
                $currentselected = array();
                foreach($additionaleditors as $u) {
                    $currentselected[] = $u->id . '_user';
                }
                $mform->getElement('sharingusers')->setSelected($currentselected);
            }
            /************************
            * End of sharing/permissions area
            *************************/




            /*----------------------
            *   Buttons
            *----------------------*/
            $this->add_action_buttons(!$embed);

        }
    }

}