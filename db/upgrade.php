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
 * Plugin upgrade steps are defined here.
 *
 * @package     mod_website
 * @category    upgrade
 * @copyright   2022 Michael Vangelovski <michael.vangelovski@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/upgradelib.php');

/**
 * Execute mod_website upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_website_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // For further information please read {@link https://docs.moodle.org/dev/Upgrade_API}.
    //
    // You will also have to create the db/install.xml file by using the XMLDB Editor.
    // Documentation for the XMLDB Editor can be found at {@link https://docs.moodle.org/dev/XMLDB_editor}.

    if ($oldversion < 2022102600) {

        // Define table website_site_menus to be created.
        $table = new xmldb_table('website_site_menus');

        // Adding fields to table website_site_menus.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('siteid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('json', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table website_site_menus.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_siteid', XMLDB_KEY_FOREIGN, ['siteid'], 'website_sites', ['id']);

        // Conditionally launch create table for website_site_menus.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define field type to be added to website_site_blocks.
        $table = new xmldb_table('website_site_blocks');
        $field = new xmldb_field('type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null, 'siteid');

        // Conditionally launch add field type.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }  

        // Website savepoint reached.
        upgrade_mod_savepoint(true, 2022102600, 'website');
    }

    if ($oldversion < 2022102601) {

        // Define field title to be added to website_site_sections.
        $table = new xmldb_table('website_site_sections');
        $field = new xmldb_field('title', XMLDB_TYPE_CHAR, '250', null, XMLDB_NOTNULL, null, null, 'siteid');

        // Conditionally launch add field title.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field sectionoptions to be added to website_site_sections.
        $field = new xmldb_field('sectionoptions', XMLDB_TYPE_TEXT, null, null, null, null, '', 'layout');

        // Conditionally launch add field sectionoptions.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
  

        // Website savepoint reached.
        upgrade_mod_savepoint(true, 2022102601, 'website');
    }

    if ($oldversion < 2022102602) {

        // Define field grade to be added to website.
        $table = new xmldb_table('website');
        $field = new xmldb_field('grade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'dynamic');

        // Conditionally launch add field grade.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Website savepoint reached.
        upgrade_mod_savepoint(true, 2022102602, 'website');
    }


    return true;
}
