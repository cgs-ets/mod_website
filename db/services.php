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
 * Plugin external functions and services are defined here.
 *
 * @package   mod_website
 * @category  external
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_website_apicontrol' => [
        'classname'     => 'mod_website\external\api',
        'methodname'    => 'apicontrol',
        'classpath'     => '',
        'description'   => 'API control',
        'type'          => 'write',
        'loginrequired' => true,
        'ajax'          => true,
    ],



    'mod_website_grade_student_site' => [
        'classname' => 'mod_website\external\api', // Class containing a reference to the external function.
        'methodname' => 'grade_student_site', // External function name.
        'description' => 'Grade student file  ', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true  // Is this service available to 'internal' ajax calls.
    ],


    'mod_website_save_quick_grading' => [
        'classname' => 'mod_website\external\api', // Class containing a reference to the external function.
        'methodname' => 'save_quick_grading', // External function name.
        'description' => 'Save grading and comment  ', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true  // Is this service available to 'internal' ajax calls.
    ],

    'mod_website_get_participant' => [
        'classname' => 'mod_website\external\api', // Class containing a reference to the external function.
        'methodname' => 'get_participant', // External function name.
        'description' => 'Get participant details  ', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true  // Is this service available to 'internal' ajax calls.
    ],

    'mod_website_get_participants' => [
        'classname' => 'mod_website\external\api', // Class containing a reference to the external function.
        'methodname' => 'list_participants', // External function name.
        'description' => 'Get participant details  ', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true  // Is this service available to 'internal' ajax calls.
    ],

    'mod_website_get_next_participant_details' => [
        'classname' => 'mod_website\external\api', // Class containing a reference to the external function.
        'methodname' => 'get_participant_by_id', // External function name.
        'description' => 'Get participant file details  ', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true  // Is this service available to 'internal' ajax calls.
    ],
];