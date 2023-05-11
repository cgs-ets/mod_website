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
 * @package mod_website
 * @subpackage backup-moodle2
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_website_activity_task
 */

/**
 * Define the complete website structure for backup, with file and id annotations
 */
class backup_website_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        //$userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $website = new backup_nested_element('website', array('id'), array(
            'course', 'name', 'timecreated', 'timemodified', 'intro',
            'introformat', 'distribution', 'groups', 'grade', 'alloweditingfromdate', 'cutoffdate'));

        $sites = new backup_nested_element('sites');
        $site = new backup_nested_element('site', array('id'), array(
            'websiteid', 'cmid', 'creatorid', 'userid', 'siteoptions', 'deleted', 'timecreated', 'timemodified'
        ));

        $pages = new backup_nested_element('pages');
        $page = new backup_nested_element('page', array('id'), array(
            'siteid', 'title', 'sections', 'sort', 'hidden', 'deleted', 'timecreated', 'timemodified'
        ));

        $sections = new backup_nested_element('sections');
        $section = new backup_nested_element('section', array('id'), array(
            'siteid', 'title', 'layout', 'sectionoptions', 'blocks', 'hidden', 'deleted', 'timecreated', 'timemodified'
        ));

        $blocks = new backup_nested_element('blocks');
        $block = new backup_nested_element('block', array('id'), array(
            'siteid', 'type', 'content', 'hidden', 'deleted', 'timecreated', 'timemodified'
        ));

        $menus = new backup_nested_element('menus');
        $menu = new backup_nested_element('menu', array('id'), array(
            'siteid', 'json', 'timecreated', 'timemodified'
        ));

        // Build the tree
        $website->add_child($sites);
        $sites->add_child($site);

        $site->add_child($pages);
        $pages->add_child($page);

        $site->add_child($sections);
        $sections->add_child($section);

        $site->add_child($blocks);
        $blocks->add_child($block);

        $site->add_child($menus);
        $menus->add_child($menu);

        // Define sources
        $website->set_source_table('website', array('id' => backup::VAR_ACTIVITYID));
        $site->set_source_table('website_sites', array('cmid' => backup::VAR_MODID, 'websiteid' => backup::VAR_ACTIVITYID));
        $page->set_source_table('website_site_pages', array('siteid' => backup::VAR_PARENTID));
        $section->set_source_table('website_site_sections', array('siteid' => backup::VAR_PARENTID));
        $block->set_source_table('website_site_blocks', array('siteid' => backup::VAR_PARENTID));
        $menu->set_source_table('website_site_menus', array('siteid' => backup::VAR_PARENTID));

        // Define file annotations
        $website->annotate_files('mod_website', 'intro', null); // This file area hasn't itemid
        $page->annotate_files('mod_website', 'bannerimage', 'id');
        $block->annotate_files('mod_website', 'content', 'id');
        $block->annotate_files('mod_website', 'buttonfile', 'id');
        $block->annotate_files('mod_website', 'picturebutton', 'id');

        // Return the root element (website), wrapped into standard activity structure
        return $this->prepare_activity_structure($website);
    }
}
