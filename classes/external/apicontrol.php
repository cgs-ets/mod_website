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
 * Provides {@link mod_website\external\apicontrol} trait.
 *
 * @package   mod_website
 * @category  external
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

namespace mod_website\external;

defined('MOODLE_INTERNAL') || die();

use external_function_parameters;
use external_value;
use context_user;

require_once($CFG->dirroot.'/mod/website/lib.php');

/**
 * Trait implementing the external function mod_website_apicontrol.
 */
trait apicontrol {

    /**
     * Describes the structure of parameters for the function.
     *
     * @return external_function_parameters
     */
    public static function apicontrol_parameters() {
        return new external_function_parameters([
            'action' =>  new external_value(PARAM_RAW, 'Action'),
            'data' => new external_value(PARAM_RAW, 'Data to process'),
        ]);
    }

    /**
     * API Controller
     *
     * @param int $query The search query
     */
    public static function apicontrol($action, $data) {
        global $USER, $OUTPUT, $PAGE;

        // Setup context.
        $context = \context_user::instance($USER->id);
        self::validate_context($context);

        // Validate params.
        self::validate_parameters(self::apicontrol_parameters(), compact('action', 'data'));

        if ($action == 'update_mode') {
            $data = json_decode($data);
            if ($data->mode) {
                website_turn_editing_on();
                return 'Saved edit on preference';
            } else {
                website_turn_editing_off();
                return 'Saved edit off preference';
            }
        }

        if ($action == 'reorder_blocks') {
            $data = json_decode($data);
            $section = new \mod_website\section($data->sectionid);
            $section->set('blocks', $data->blocks);
            $section->update();
            return 1;
        }

        if ($action == 'reorder_sections') {
            $data = json_decode($data);
            $page = new \mod_website\page($data->pageid);
            $page->set('sections', $data->sections);
            $page->update();
            return 1;
        }

        if ($action == 'delete_block') {
            $block = new \mod_website\block($data);
            $block->delete();
            return 1;
        }
        
        if ($action == 'delete_section') {
            $section = new \mod_website\section($data);
            $section->delete();
            return 1;
        }

        return 0;
    }

    /**
     * Describes the structure of the function return value.
     *
     * @return external_single_structure
     */
    public static function apicontrol_returns() {
         return new external_value(PARAM_RAW, 'Result');
    }

}