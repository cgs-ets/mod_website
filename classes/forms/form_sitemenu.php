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

class form_sitemenu extends \moodleform {

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT, $USER, $DB;

        $mform =& $this->_form;
        $menu = $this->_customdata['menu'];
        $unusedpages = $this->_customdata['unusedpages'];
        $allpages = $this->_customdata['allpages'];
        $embed = $this->_customdata['embed'];
        
        $mform->addElement('text', 'menujson', 'MenuJSON');
        $mform->setType('menujson', PARAM_RAW);
        
        $html = $OUTPUT->render_from_template('mod_website/_edit_menu', [
            'menu' => $menu,
            'unusedpages' => $unusedpages,
            'allpages' => $allpages,
        ]);
        $mform->addElement('html', $html);


        /*----------------------
        *   Buttons
        *----------------------*/
        $this->add_action_buttons(!$embed);
    }

}