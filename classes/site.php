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
    const TABLE_WEBSITE = 'website';

    private $data = array();

    public $menu = null;
    public $currentpage = null;
    public $homepageid = 0;
    public $numpages = 0;

    private static function required_data() {
        return array('websiteid', 'cmid', 'creatorid', 'userid', 'title');
    }

    private static function required_related() {
        return array('user', 'course', 'website', 'modulecontext');
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
            //    array(
            //        'id' => $page->get_id(), 
            //        'children' => []
            //    )
            //]),
        );
        $menu = new \mod_website\menu();
        $menu->create($menudata);

        // Add the homepage to the site.
        $this->data->siteoptions = json_encode(array(
            'homepage' => $page->get_id(),
            'menu' => $menu->get_id(),
        ));
        $DB->update_record(static::TABLE, $this->data);

        return $this;
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

        if ( ! property_exists($this->data, 'siteoptions')) {
            return;
        }

        $siteoptions = json_decode($this->data->siteoptions);

        // Homepage id.
        if (property_exists($siteoptions, 'homepage')) {
            $this->homepageid = $siteoptions->homepage;
        }

        // Menu.
        $menuid = 0;
        if (property_exists($siteoptions, 'menu')) {
            $menuid = $siteoptions->menu;
        }
        $this->menu = new \mod_website\menu($menuid);

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
        if ($pageid) {
            $this->currentpage = new \mod_website\page($pageid);
        } else {
            if ($this->homepageid) {
                $this->currentpage = new \mod_website\page($this->homepageid);
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

        // Editing URLs
        $canedit = false;
        if ($this->data->userid == $related['user']->id) {
            $canedit = true;

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
        }

        // Go back / Course URL
        $courseurl = new \moodle_url('/course/view.php', array(
            'id' => $related['course']->id,
        ));
        $related['course']->url = $courseurl->out(false);

        // Menu
        if ($this->menu->get_id()) {
            $menu = $this->menu->export([]);
        }

        // Current page
        if ($this->currentpage) {
            $currentpage = $this->currentpage->export(array(
                'cmid' => $this->data->cmid,
                'modulecontext' => $related['modulecontext'],
                'website' => $related['website']
            ));
        }

        return array(
            'canedit' => $canedit,
            'editpageurl' => $editpageurl->out(false),
            'newpageurl' => $newpageurl->out(false),
            'editmenuurl' => $editmenuurl ? $editmenuurl->out(false) : '',
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
        );
    }

    /**
     * Validate required data.
     *
     * @return array
     */
    private function validate_data() {
        foreach (static::required_data() as $attribute) {
            if ((! array_key_exists($attribute, $this->data)) || empty($this->data[$attribute])) {
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
        foreach (static::required_related() as $attribute) {
            if ((! array_key_exists($attribute, $related)) || empty($related[$attribute])) {
                throw new \coding_exception('Site is missing required related data: ' . $attribute);
            }
        }
    }

    public function can_user_edit() {
        global $USER;
        return ($this->get_userid() === $USER->id);
    }

    public function can_user_view() {

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

    public function get_id() {
        return $this->data->id;
    }

    public function get_websiteid() {
        return $this->data->websiteid;
    }

    public function get_website() {
        global $DB;
    
        $website = $DB->get_record(static::TABLE_WEBSITE, array(
            'id' => $this->data->websiteid,
        ), '*', IGNORE_MULTIPLE);

        return $website;
    }
    
    public function get_cmid() {
        return $this->data->cmid;
    }

    public function get_userid() {
        return $this->data->userid;
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
      

}