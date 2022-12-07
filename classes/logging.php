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
 * @package   mod_website
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_website;

defined('MOODLE_INTERNAL') || die();

/**
 * Provides utility functions for this plugin.
 *
 * @package   mod_website
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class logging {

    const TABLE_LOGS = 'website_change_logs';
    
    public static function log($resourcetype, $resourcekey, $data) {
        global $DB, $USER;

        // Fundamental properties.
        $record = new \stdClass();
        $record->resourcetype = $resourcetype;
        $record->resourcekey = $resourcekey;
        $record->userid = $USER->id;
        $record->logtime = time();
        $record->logdata = json_encode($data);
        
        $DB->insert_record(static::TABLE_LOGS, $record);
    }


}