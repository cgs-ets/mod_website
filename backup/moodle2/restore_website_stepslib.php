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
 * Define all the restore steps that will be used by the restore_website_activity_task
 */

 use mod_website\site;

/**
 * Structure step to restore one website activity
 */
class restore_website_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('website', '/activity/website');
        $paths[] = new restore_path_element('website_site', '/activity/website/sites/site');        
        $paths[] = new restore_path_element('website_page', '/activity/website/sites/site/pages/page');
        $paths[] = new restore_path_element('website_section', '/activity/website/sites/site/sections/section');
        $paths[] = new restore_path_element('website_block', '/activity/website/sites/site/blocks/block');
        $paths[] = new restore_path_element('website_menu', '/activity/website/sites/site/menus/menu');

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_website($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // insert the website record
        $newitemid = $DB->insert_record('website', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_website_site($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->websiteid = $this->task->get_activityid();
        $data->cmid = $this->task->get_moduleid();

        // insert the entry record
        $newitemid = $DB->insert_record('website_sites', $data);
        $this->set_mapping('website_site', $oldid, $newitemid, true);
    }

    protected function process_website_page($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->siteid = $this->get_new_parentid('website_site');
        $newitemid = $DB->insert_record('website_site_pages', $data);
        $this->set_mapping('website_page', $oldid, $newitemid, true);

        // if oldid is the homepage for the website, update the website record to use newitemid.
        $site = new Site($data->siteid);
        if ($site->homepageid == $oldid) {
            $siteoptions = json_decode($site->get_siteoptions(), true);
            $siteoptions['homepage'] = $newitemid;
            $site->set('siteoptions', json_encode($siteoptions));
            $site->update();
        }

    }

    protected function process_website_section($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->siteid = $this->get_new_parentid('website_site');
        $newitemid = $DB->insert_record('website_site_sections', $data);
        $this->set_mapping('website_section', $oldid, $newitemid, true);
        
        
        // if oldid is the sections column for any pages in this website, update the sections column to use newitemid.
        $site = new Site($data->siteid);
        $pages = $site->get_all_pages();
        foreach ($pages as $pagerec) {
            $page = new \mod_website\page($pagerec->id);
            $sections = $page->get_sections();
            foreach($sections as &$sectionid) {
                if ((int)$sectionid == (int)$oldid) {
                    $sectionid = $newitemid;
                }
            }
            $page->set('sections', json_encode($sections));
            $page->update();
        }

    }

    protected function process_website_block($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->siteid = $this->get_new_parentid('website_site');
        $newitemid = $DB->insert_record('website_site_blocks', $data);
        $this->set_mapping('website_block', $oldid, $newitemid, true);
        
        // if oldid is the blocks column for any sections in this website, update the blocks column to use newitemid.
        $site = new Site($data->siteid);
        $pages = $site->get_all_pages();
        foreach ($pages as $pagerec) {
            $page = new \mod_website\page($pagerec->id);
            $sections = $page->get_sections();
            foreach($sections as $sectionid) {
                $section = new \mod_website\section($sectionid);
                $blocks = $section->get_blocks();
                foreach($blocks as &$blockid) {
                    if ((int)$blockid == (int)$oldid) {
                        $blockid = $newitemid;
                    }
                }
                $section->set('blocks', json_encode($blocks));
                $section->update();
            }
        }

    }

    protected function process_website_menu($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->siteid = $this->get_new_parentid('website_site');
        $menu = json_decode($data->json, true);
        foreach($menu as $i => $item) {
            $newid = $this->get_mappingid('website_page', $item['id']);
            if ($newid) {
                $menu[$i]['id'] = $newid;
                foreach($item['children'] as $j => $childitem) {
                    $newid = $this->get_mappingid('website_page', $item['id']);
                    if ($newid) {
                        $menu[$i]['children'][$j]['id'] = $newid;
                    } else {
                        unset($menu[$i]['children'][$j]);
                    }
                }
            } else {
                unset($menu[$i]);
            }
        }
        $data->json = json_encode($menu);
        $newitemid = $DB->insert_record('website_site_menus', $data);
        $this->set_mapping('website_menu', $oldid, $newitemid, true);

        // if oldid is the menu for the website, update the website record to use newitemid.
        $site = new Site($data->siteid);
        if ($site->menuid == $oldid) {
            $siteoptions = json_decode($site->get_siteoptions(), true);
            $siteoptions['menu'] = $newitemid;
            $site->set('siteoptions', json_encode($siteoptions));
            $site->update();
        }
    }

    protected function after_execute() {
        // Add website related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_website', 'intro', null);
        $this->add_related_files('mod_website', 'bannerimage', 'website_page');
        $this->add_related_files('mod_website', 'content', 'website_block');
        $this->add_related_files('mod_website', 'buttonfile', 'website_block');
        $this->add_related_files('mod_website', 'picturebutton', 'website_block');
    }
}
