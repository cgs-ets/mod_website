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

use mod_website\utils;
use mod_website\logging;
use mod_website\permissions;

/**
 * Provides utility functions for this plugin.
 *
 * @package   mod_website
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class Site {

    /** The table name. */
    const TABLE = 'website_sites';
    const TABLE_PAGES = 'website_site_pages';
    const TABLE_SECTIONS = 'website_site_sections';
    const TABLE_BLOCKS = 'website_site_blocks';
    const TABLE_MENUS = 'website_site_menus';
    const TABLE_WEBSITE = 'website';
    const TABLE_PERMISSIONS = 'website_permissions';
    

    private $data = array();

    public $menu = null;
    public $currentpage = null;
    public $homepageid = 0;
    public $menuid = 0;
    public $numpages = 0;

    private $permissions = array();

    private static function required_data() {
        return array('websiteid', 'cmid', 'creatorid', 'userid');
    }

    private static function required_related() {
        return array('course', 'website', 'modulecontext');
    }

    /**
     * Create an instance of this class.
     *
     * @param int $id If set, this is the id of an existing record, used to load the data.
     */
    public function __construct($id = 0) {
        global $CFG;

        if ($id > 0) {
            return $this->read($id);
        }
    }

    /**
     * create a new site record in the db and return a site instance.
     *
     * @param $data
     * @return static
     */
    public function create($data) {
        global $DB;

        $this->data = $data;

        $this->validate_data();

        $this->data['timecreated'] = time();
        $this->data['timemodified'] = time();

        $pagetitle = $this->data['title'];
        unset($this->data['title']);
        
        $id = $DB->insert_record(static::TABLE, $this->data);
        $this->read($id);

        // Every site has an initial homepage.
        $pagedata = array(
            'siteid' => $id,
            'title' => $pagetitle,
            'sections' => '',
        );
        $page = new \mod_website\page();
        $page->create($pagedata);

        // Every site has an initial menu.
        $menudata = array(
            'siteid' => $id,
            'json' => json_encode([]),
        );
        $menu = new \mod_website\menu();
        $menu->create($menudata);

        // Add the homepage to the site.
        $this->data->siteoptions = json_encode(array(
            'homepage' => $page->get_id(),
            'menu' => $menu->get_id(),
        ));
        $DB->update_record(static::TABLE, $this->data);

        logging::log('Site', $id, array(
            'event' => 'Site created'
        ));

        return $this;
    }

    /**
     * create a new site record in the db and return a site instance.
     *
     * @param $data
     * @return static
     */
    public function create_from_template($data, $oldsiteid, $attemptreplacelinks = false) {
        global $DB;

        // First check if siteid exists.
        $oldsite = new \mod_website\site($oldsiteid);
        if (!$oldsite->get_id()) {
            return false;
        }

        /*****
         * Create the new site.
         *****/
        $this->data = $data;
        $this->validate_data();
        $this->data['timecreated'] = time();
        $this->data['timemodified'] = time();
        $pagetitle = $this->data['title'];
        unset($this->data['title']);
        $id = $DB->insert_record(static::TABLE, $this->data);
        logging::log('Site', $id, array(
            'event' => 'Site stub created for template'
        ));
        $this->read($id);

        /*****
         * Copy everything from template site.
         *****/
        // Copy menus.
        $menucopies = $this->copy_menus_from($oldsiteid);

        // Copy pages.
        $pagecopies = $this->copy_pages_from($oldsiteid);

        // Copy sections.
        $sectioncopies = $this->copy_sections_from($oldsiteid);

        // Copy blocks.
        $blockcopies = $this->copy_blocks_from($oldsiteid);


        /*****
         * Update id references throughout.
         *****/
        // Sites siteoptions have a homepage and menu.
        $this->data->siteoptions = json_encode(array(
            'homepage' => $pagecopies[$oldsite->homepageid],
            'menu' => $menucopies[$oldsite->menuid],
        ));
        $DB->update_record(static::TABLE, $this->data);
        $this->read($id);

        // Menus have pages.
        foreach ($menucopies as $copy) {
            if ($copy == 0) { continue; }
            $newmenu = new \mod_website\menu($copy);
            $items = $newmenu->menu_to_array();
            foreach ($items as &$item) {
                $oldpage = $item['id'];
                if (isset($pagecopies[$oldpage])) {
                    $item['id'] = $pagecopies[$oldpage];
                    foreach ($item['children'] as &$child) {
                        $oldpage = $child['id'];
                        if (isset($pagecopies[$oldpage])) {
                            $child['id'] = $pagecopies[$oldpage];
                        } else {
                            unset($child);
                        }
                    }
                } else {
                    unset($item);
                }
            }
            $newmenu->update_menu_from_array($items);
        }
        
        // Pages have sections.
        foreach ($pagecopies as $copy) {
            if ($copy == 0) { continue; }
            $newpage = new \mod_website\page($copy);
            $sections = $newpage->get_sections();
            foreach($sections as &$section) {
                $section = $sectioncopies[$section];
            }
            $newpage->set('sections', json_encode($sections));
            $newpage->update();
        }

        // Sections have blocks.
        foreach ($sectioncopies as $copy) {
            if ($copy == 0) { continue; }
            $newsection = new \mod_website\section($copy);
            $blocks = $newsection->get_blocks();
            foreach($blocks as &$block) {
                $block = $blockcopies[$block];
            }
            $newsection->set('blocks', json_encode($blocks));
            $newsection->update();
        }

        /*****
         * Copy files.
         *****/
        $oldcontext = \context_module::instance($oldsite->get_cmid());
        $newcontext = \context_module::instance($this->get_cmid());
        $fs = get_file_storage();

        // Page banners.
        foreach ($pagecopies as $oldid => $newid) {
            if ($oldid == 0) { continue; }
            if ($files = $fs->get_area_files($oldcontext->id, 'mod_website', 'bannerimage', $oldid, "filename", true)) {
                foreach ($files as $file) {
                    $newrecord = new \stdClass();
                    $newrecord->contextid = $newcontext->id;
                    $newrecord->itemid = $newid;
                    $fs->create_file_from_storedfile($newrecord, $file);
                }
            }
        }

        // Block button link to file.
        foreach ($blockcopies as $oldid => $newid) {
            if ($oldid == 0) { continue; }
            if ($files = $fs->get_area_files($oldcontext->id, 'mod_website', 'buttonfile', $oldid, "filename", true)) {
                foreach ($files as $file) {
                    $newrecord = new \stdClass();
                    $newrecord->contextid = $newcontext->id;
                    $newrecord->itemid = $newid;
                    $fs->create_file_from_storedfile($newrecord, $file);
                }
            }
        }

        // Block button picture.
        foreach ($blockcopies as $oldid => $newid) {
            if ($oldid == 0) { continue; }
            if ($files = $fs->get_area_files($oldcontext->id, 'mod_website', 'picturebutton', $oldid, "filename", true)) {
                foreach ($files as $file) {
                    $newrecord = new \stdClass();
                    $newrecord->contextid = $newcontext->id;
                    $newrecord->itemid = $newid;
                    $fs->create_file_from_storedfile($newrecord, $file);
                }
            }
        }

        // Block content files.
        foreach ($blockcopies as $oldid => $newid) {
            if ($oldid == 0) { continue; }
            if ($files = $fs->get_area_files($oldcontext->id, 'mod_website', 'content', $oldid, "filename", true)) {
                foreach ($files as $file) {
                    $newrecord = new \stdClass();
                    $newrecord->contextid = $newcontext->id;
                    $newrecord->itemid = $newid;
                    $fs->create_file_from_storedfile($newrecord, $file);
                }
            }
        }

        // Attempt to replace links to old site within new site.
        if ($attemptreplacelinks) {
            foreach ($blockcopies as $blockid) {
                if ($blockid == 0) { continue; }
                $block = new \mod_website\block($blockid);
                $content = $block->get_content();
                
                // Replace site param.
                $content = str_replace('site=' . $oldsiteid, 'site=' . $this->get_id(), $content);

                // Replace page params.
                foreach ($pagecopies as $oldpageid => $newpageid) {
                    $content = str_replace('page=' . $oldpageid, 'page=' . $newpageid, $content);
                }

                $block->set('content', $content);
                $block->update();
            }
        }

        logging::log('Site', $id, array(
            'event' => 'Copy from template complete'
        ));

        return $this;
    }

    public function copy_menus_from($siteid) {
        global $DB;
        $copies = array(0 => 0);
        $menus = $DB->get_records(static::TABLE_MENUS, array(
            'siteid' => $siteid,
        ));
        foreach ($menus as $menu) {
            // Keep track of the old id.
            $oldid = $menu->id;
            unset($menu->id);
            // Update the data.
            $menu->siteid = $this->get_id();
            $newmenu = new \mod_website\menu();
            $newmenu = $newmenu->create($menu);
            $copies[$oldid] = intval($newmenu->get_id());
        }
        return $copies;
    }

    public function copy_pages_from($siteid) {
        global $DB;
        $copies = array(0 => 0);
        $pages = $DB->get_records(static::TABLE_PAGES, array(
            'siteid' => $siteid,
        ));
        foreach ($pages as $page) {
            // Keep track of the old id.
            $oldid = $page->id;
            unset($page->id);
            // Update the data.
            $page->siteid = $this->get_id();
            $newpage = new \mod_website\page();
            $newpage = $newpage->create($page);
            $copies[$oldid] = intval($newpage->get_id());
        }
        return $copies;
    }

    public function copy_sections_from($siteid) {
        global $DB;
        $copies = array(0 => 0);
        $sections = $DB->get_records(static::TABLE_SECTIONS, array(
            'siteid' => $siteid,
        ));
        foreach ($sections as $section) {
            // Keep track of the old id.
            $oldid = $section->id;
            unset($section->id);
            // Update the data.
            $section->siteid = $this->get_id();
            $newsection = new \mod_website\section();
            $newsection = $newsection->create($section);
            $copies[$oldid] = intval($newsection->get_id());
        }
        return $copies;
    }

    public function copy_blocks_from($siteid) {
        global $DB;
        $copies = array(0 => 0);
        $blocks = $DB->get_records(static::TABLE_BLOCKS, array(
            'siteid' => $siteid,
        ));
        foreach ($blocks as $block) {
            // Keep track of the old id.
            $oldid = $block->id;
            unset($block->id);
            // Update the data.
            $block->siteid = $this->get_id();
            $newblock = new \mod_website\block();
            $newblock = $newblock->create($block);
            $newblock->set('content', $block->content);
            $newblock->update();
            $copies[$oldid] = intval($newblock->get_id());
        }
        return $copies;
    }

    /* This is used to copy a single page based on a provided template URL. Used when creating page per student distribution */
    public function copy_page_from($pageid) {
        global $DB;
        $copies = array();
        $page = $DB->get_record(static::TABLE_PAGES, array(
            'id' => $pageid,
        ));
        unset($page->id);
        // Update the data.
        $page->siteid = $this->get_id();
        $newpage = new \mod_website\page();
        $newpage = $newpage->create($page);
        return intval($newpage->get_id());
    }

    /* This is used to copy a single page based on a provided template URL. Used when creating page per student distribution */
    public function copy_sections_from_page($pageid) {
        global $DB;
        $copies = array(0 => 0);
        $page = new \mod_website\page($pageid); 
        foreach ($page->sections as $section) {
            // Keep track of the old id.
            $oldid = $section->get_id();
            // Update the data.
            $section->set_siteid($this->get_id());
            $newsection = $section->save_as();
            $copies[$oldid] = intval($newsection->get_id());
        }
        return $copies;
    }

    /* This is used to copy a single page based on a provided template URL. Used when creating page per student distribution */
    public function copy_blocks_from_page($pageid) {
        global $DB;
        $copies = array(0 => 0);
        $page = new \mod_website\page($pageid); 
        foreach ($page->sections as $section) {
            foreach ($section->blocks as $block) {
                // Keep track of the old id.
                $oldid = $block->get_id();
                // Update the data.
                $block->set_siteid($this->get_id());
                $newblock = $block->save_as();
                $copies[$oldid] = intval($newblock->get_id());
            }
        }
        return $copies;
    }

    /**
     * Load the data from the DB.
     *
     * @param $id
     * @return static
     */
    final public function read($id) {
        global $DB;

        $this->data = $DB->get_record(static::TABLE, array('id' => $id, 'deleted' => 0), '*', IGNORE_MULTIPLE);

        $this->read_siteoptions();
        $this->load_page();

        return $this;
    }


    /**
     * Load the data from the DB.
     *
     * @return static
     */
    final public function read_from_cmid($cmid) {
        global $DB;

        $this->data = $DB->get_record(static::TABLE, array('cmid' => $cmid, 'deleted' => 0), '*', IGNORE_MULTIPLE);

        $this->read_siteoptions();
        $this->load_page();

        return $this;
    }

    /**
     * Load the data from the DB.
     *
     * @return static
     */
    final public function read_from_websiteid($websiteid) {
        global $DB;

        $this->data = $DB->get_record(static::TABLE, array('websiteid' => $websiteid, 'deleted' => 0), '*', IGNORE_MULTIPLE);

        $this->read_siteoptions();
        $this->load_page();

        return $this;
    }

    /**
     * Load the data from the DB.
     *
     * @return static
     */
    final public function read_for_studentid($websiteid, $studentid) {
        global $DB;

        $this->data = $DB->get_record(static::TABLE, array(
            'userid' => $studentid, 
            'deleted' => 0
        ), '*', IGNORE_MULTIPLE);

        $this->read_siteoptions();
        $this->load_page();

        return $this;
    }

    /**
     * update site data.
     *
     * @param $data
     * @return static
     */
    public function update() {
        global $DB;

        if ($this->data->id) {
            $this->validate_data();
            $DB->update_record(static::TABLE, $this->data);
        }
        
        return $this->data->id;
    }

    /**
     * Fetch site and page.
     *
     * @return static
     */
    final public function fetch($pageid) {
        global $DB;

        $this->data = $DB->get_record(static::TABLE, array('id' => $this->get_id(), 'deleted' => 0), '*', IGNORE_MULTIPLE);

        $this->read_siteoptions();
        $this->load_page($pageid);

        return $this;
    }

    /**
     * Construct the site options.
     *
     * @return static
     */
    final public function read_siteoptions() {
        global $DB;

        if ( !$this->data || !property_exists($this->data, 'siteoptions')) {
            return;
        }

        $siteoptions = json_decode($this->data->siteoptions);
        if (empty($siteoptions)) {
            return;
        }

        // Homepage id.
        $this->homepageid = 0;
        if (property_exists($siteoptions, 'homepage')) {
            $this->homepageid = $siteoptions->homepage;
        }

        // Menu.
        $this->menuid = 0;
        if (property_exists($siteoptions, 'menu')) {
            $this->menuid = $siteoptions->menu;
        }
        $this->menu = new \mod_website\menu($this->menuid);

        // Number of pages.
        $this->numpages = $DB->count_records(static::TABLE_PAGES, array(
            'siteid' => $this->get_id(),
            'hidden' => 0,
            'deleted' => 0,
        ));

    }

    /**
     * Add a page to the menu.
     *
     * @return static
     */
    final public function add_page_to_menu_top($pageid) {
        global $DB;

        if (empty($pageid) || empty($this->menu)) {
            return;
        }

        $menuarr = $this->menu->menu_to_array();
        $menuarr[] = array(
            'id' => $pageid,
            'children' => [],
        );
        $this->menu->update_menu_from_array($menuarr);
    }

    /**
     * Load a site page.
     *
     * @return static
     */
    final public function load_page($pageid = 0) {
        $page = new \mod_website\page();
        if ($pageid) {
            $this->currentpage = $page->read_for_site($this->get_id(), $pageid);
        } else {
            if ($this->homepageid) {
                $this->currentpage = $page->read_for_site($this->get_id(), $this->homepageid);
            }
        }
    }


    /**
     * Serialise data based on related info to a structure ready for rendering.
     *
     * @return array
     */
    public function export($related) {
        $this->validate_related($related);

        $output = array();

        // Is this user a site editor?
        $caneditsite = $this->can_user_edit();

        // Is this user an editor of this page?
        $page = new \mod_website\page();
        $page->read_for_site($this->get_id(), $related['currentpage']->get_id());
        if (empty($page->get_id())) {
            return;
        }
        $caneditpage = $page->can_user_edit();

        // Check availaibility conditions.
        if (
            ($related['website']->alloweditingfromdate && $related['website']->alloweditingfromdate > time()) ||
            ($related['website']->cutoffdate && $related['website']->cutoffdate <= time())
        ) {
            $caneditsite = $caneditpage = false;
        }
        
        $siteurl = new \moodle_url('/mod/website/site.php', array(
            'site' => $this->data->id,
        ));

        // Editing URLs
        $editsiteurl = new \moodle_url('/mod/website/edit-site.php', array(
            'site' => $this->data->id,
            'page' => $this->currentpage->get_id(),
        ));
        $sitepermissionsurl = new \moodle_url('/mod/website/edit-permissions.php', array(
            'type' => 'site',
            'site' => $this->data->id,
            'page' => $this->currentpage->get_id(),
        ));
        $pagepermissionsurl = clone($sitepermissionsurl);
        $pagepermissionsurl->param('type', 'page');
        $editpageurl = new \moodle_url('/mod/website/edit-page.php', array(
            'site' => $this->data->id,
            'page' => $this->currentpage->get_id(),
        ));
        $newpageurl = new \moodle_url('/mod/website/edit-page.php', array(
            'site' => $this->data->id,
        ));
        $editmenuurl = new \moodle_url('/mod/website/edit-menu.php', array(
            'site' => $this->data->id,
            'menu' => $this->menu->get_id(),
            'page' => $this->currentpage->get_id(),
        ));
        $newsectionurl = new \moodle_url('/mod/website/edit-section.php', array(
            'site' => $this->data->id,
            'page' => $this->currentpage->get_id(),
        ));

        // Go back / Course URL
        $courseurl = new \moodle_url('/course/view.php', array(
            'id' => $related['course']->id,
        ));
        $related['course']->url = $courseurl->out(false);

        // Menu
        if ($this->menu->get_id()) {
            $menu = $this->menu->export(array(
                'mode' => $related['mode'],
            ));
        }

        // Current page
        if ($this->currentpage) {
            $currentpage = $this->currentpage->export(array(
                'cmid' => $this->data->cmid,
                'modulecontext' => $related['modulecontext'],
                'website' => $related['website']
            ));
        }

        $output = array(
            'caneditsite' => $caneditsite,
            'caneditpage' => $caneditpage,
            'sitepermissionsurl' => $sitepermissionsurl->out(false),
            'pagepermissionsurl' => $pagepermissionsurl->out(false),
            'editsiteurl' => $editsiteurl->out(false),
            'editpageurl' => $editpageurl->out(false),
            'newpageurl' => $newpageurl->out(false),
            'editmenuurl' => $editmenuurl->out(false),
            'newsectionurl' => $newsectionurl->out(false),
            'id' => $this->data->id,
            'websiteid' => $this->data->websiteid,
            'cmid' => $this->data->cmid,
            'creatorid' => $this->data->creatorid,
            'userid' => $this->data->userid,
            'menu' => $menu,
            'course' => (array) $related['course'],
            'website' => (array) $related['website'],
            'page' => $currentpage,
            'mode' => $related['mode'],
            'editing' => $related['mode'] == 'edit',
            'siteurl' => $siteurl->out(false),
        );

        // Embedded Form URLs.
        $sitepermissionsurl->param('embed', 1);
        $pagepermissionsurl->param('embed', 1);
        $editsiteurl->param('embed', 1);
        $editpageurl->param('embed', 1);
        $newpageurl->param('embed', 1);
        $editmenuurl->param('embed', 1);
        $newsectionurl->param('embed', 1);
        $output['embedded_sitepermissionsurl'] = $sitepermissionsurl->out(false);
        $output['embedded_pagepermissionsurl'] = $pagepermissionsurl->out(false);
        $output['embedded_editsiteurl'] = $editsiteurl->out(false);
        $output['embedded_editpageurl'] = $editpageurl->out(false);
        $output['embedded_newpageurl'] = $newpageurl->out(false);
        $output['embedded_editmenuurl'] = $editmenuurl->out(false);
        $output['embedded_newsectionurl'] = $newsectionurl->out(false);

        return (object) $output;
    }

    /**
     * Validate required data.
     *
     * @return array
     */
    private function validate_data() {
        $data = (array) $this->data;
        foreach (static::required_data() as $attribute) {
            if ((! array_key_exists($attribute, $data)) || empty($data[$attribute])) {
                throw new \coding_exception('Site is missing required data: ' . $attribute);
            }
        }
    }

    /**
     * Validate required relateds.
     *
     * @return array
     */
    private function validate_related($related) {
        $related = (array) $related;
        foreach (static::required_related() as $attribute) {
            if ((! array_key_exists($attribute, $related)) || empty($related[$attribute])) {
                throw new \coding_exception('Site is missing required related data: ' . $attribute);
            }
        }
    }
    
    public function set($property, $value) {
        $this->data->$property = $value;
    }

    public function get_id() {
        return isset($this->data->id) ? $this->data->id : null;
    }

    public function get_websiteid() {
        return isset($this->data->websiteid) ? $this->data->websiteid : null;
    }

    public function get_website() {
        global $DB;
    
        $website = $DB->get_record(static::TABLE_WEBSITE, array(
            'id' => $this->data->websiteid,
        ), '*', IGNORE_MULTIPLE);

        return $website;
    }
    
    public function get_cmid() {
        return isset($this->data->cmid) ? $this->data->cmid : null;
    }

    public function get_userid() {
        return isset($this->data->userid) ? $this->data->userid : null;
    }

    public function get_section($id) {
        global $DB;
    
        $section = $DB->get_record(static::TABLE_SECTIONS, array(
            'id' => $id, 
            'siteid' => $this->data->id,
            'deleted' => 0,
        ), '*', IGNORE_MULTIPLE);

        return $section;
    }

    public function get_block($id) {
        global $DB;
    
        $block = $DB->get_record(static::TABLE_BLOCKS, array(
            'id' => $id, 
            'siteid' => $this->data->id,
            'deleted' => 0,
        ), '*', IGNORE_MULTIPLE);

        return $block;
    }

    public function get_unused_pages() {
        global $DB;

        $pageids = array();
        foreach ($this->menu->menu_to_array() as $menuitem) {
            $pageids[] = $menuitem['id'];
            foreach ($menuitem['children'] as $childitem) {
                $pageids[] = $childitem['id'];
            }
        }

        // If there is nothing in the menu then make sure sql works and all pages are returned.
        if (empty($pageids)) {
            $pageids[] = '0';
        }

        list($insql, $inparams) = $DB->get_in_or_equal($pageids);
        $sql = "SELECT *
                FROM {" . static::TABLE_PAGES . "}
                WHERE siteid = ?
                AND hidden = 0
                AND deleted = 0
                AND id NOT IN (SELECT id FROM {" . static::TABLE_PAGES . "} WHERE id " . $insql . ")";
        $unused = $DB->get_records_sql($sql, array_merge([$this->data->id], $inparams));

        return array_values($unused);
    }


    public function get_all_pages() {
        global $DB;

        $pages = $DB->get_records(static::TABLE_PAGES, array(
            'siteid' => $this->data->id,
            'deleted' => 0,
        ));

        return array_values($pages);
    }

    public function promote_to_home($pageid) {
        // Make sure the page is real and visible.
        $page = new \mod_website\page();
        $page->read_for_site($this->get_id(), $pageid);
        if (empty($page->get_id())) {
            return;
        }
        $page->toggle_hide(0);

        // Set as site homepage.
        $this->homepageid = $pageid;
        $siteoptions = json_decode($this->data->siteoptions, true);
        $siteoptions['homepage'] = $pageid;
        $this->data->siteoptions = json_encode($siteoptions);
        $this->update();
    }

    public function can_user_edit() {
        global $USER, $DB;

        // Site creator can always edit.
        if ($this->get_userid() === $USER->id) {
            return true;
        }

        // Permissions.
        $permissions = $DB->get_record(static::TABLE_PERMISSIONS, array(
            'permissiontype' => 'Edit',
            'resourcetype' => 'Site',
            'resourcekey' => $this->get_id(),
            'userid' => $USER->id,
        ), '*', IGNORE_MULTIPLE);
        if ($permissions) {
            return true;
        }

        return false;
    }

    public function can_user_view() {
        global $USER;
        
        // Single site
        if ($this->get_website()->distribution === '0') {
            // Everyone can view.
            return true;
        }

        // Copy for each student.
        if ($this->get_website()->distribution === '1') {
            // Teachers, the student, and their mentors can view.
            if (utils::is_grader() || 
                $this->get_userid() === $USER->id || 
                utils::is_user_mentor_of_student($USER->id, $this->get_userid()) ) {
                return true;
            }

        }

        return false;
    }

    public function sync_permission_selections($data) {
        if (empty($this->get_id())) {
            return;
        }
        // Can only share a site if the distribution is single site.
        $website = $this->get_website();
        if ($website->distribution !== '0') {
            return;
        }
        
        permissions::sync_permission_selections('Site', $this->get_id(), $data);
    }
     
    public function load_editors() {
        global $DB; 
        
        if (empty($this->get_id())) {
            return;
        }

        // Permissions.
        $this->permissions = $DB->get_records(static::TABLE_PERMISSIONS, array(
            'resourcetype' => 'Site',
            'resourcekey' => $this->get_id(),
        ));
    }

    public function export_user() {
        $user = \core_user::get_user($this->get_userid());
        utils::load_user_display_info($user);
        return $user;
    }

    public function export_editors() {
        $editors = array();
        foreach ($this->permissions as $permission) {
            if ($permission->permissiontype == 'Edit') {
                $user = \core_user::get_user($permission->userid);
                utils::load_user_display_info($user);
                $editors[] = $user;
            }
        }
        return $editors;
    }

}