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

use mod_website\forms\form_siteblock;

require_once($CFG->libdir . '/filelib.php');

/**
 * Provides utility functions for this plugin.
 *
 * @package   mod_website
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class Menu {

    /** The table name. */
    const TABLE = 'website_site_menus';
    const TABLE_PAGES = 'website_site_pages';

    private $data = array();
    public $numpages = 0;

    private static function required_data() {
        return array('siteid');
    }

    private static function required_related() {
        return array();
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
     * create a new menu.
     *
     * @param $data
     * @return static
     */
    public function create($data) {
        global $DB;

        $this->data = (object) $data;
        $this->data->timecreated = time();
        $this->data->timemodified = time();

        $this->validate_data();
        
        $id = $DB->insert_record(static::TABLE, $this->data);

        return $this->read($id);
    }

    /**
     * Load the data from the DB.
     *
     * @param $id
     * @return static
     */
    final public function read($id) {
        global $DB;

        $this->data = $DB->get_record(static::TABLE, array('id' => $id), '*', IGNORE_MULTIPLE);

        $this->numpages = sizeof($this->menu_to_array(), 1);

        return $this;
    }

    /**
     * save a menu.
     *
     * @param $data
     * @return static
     */
    public function save($data) {
        global $DB;

        if (empty($data->id)) {
            $this->create($data);
        } else {
            //Read and update existing.
            $this->read($data->id);
            $this->data->json = $data->json;
            $this->data->timemodified = time();
            $this->validate_data();
            $DB->update_record(static::TABLE, $this->data);
        }
        
        return $this->data->id;
    }

    /**
     * Update a menu from array.
     * 
     * @param $menuarr
     * @return static
     */
    public function update_menu_from_array($menuarr) {
        global $DB;

        if (empty($this->data->id)) {
            return;
        }
        $this->data->json = json_encode($menuarr);
        $this->data->timemodified = time();
        $this->validate_data();
        $DB->update_record(static::TABLE, $this->data);
    }

    
    /**
     * Serialise data based on related info to a structure ready for rendering.
     *
     * @return array
     */
    public function menu_to_array() {
        return json_decode($this->data->json, true);
    }

    /**
     * Get the menu json.
     *
     * @return array
     */
    public function get_json() {
        if (isset($this->data->json)) {
            return $this->data->json;
        }
        return '';
    }

    /**
     * Serialise data based on related info to a structure ready for rendering.
     *
     * @return array
     */
    public function export($related = array()) {
        $this->validate_related($related);

        $menuarr = $this->menu_to_array();
        $backend = isset($related['backend']) ? $related['backend'] : false;
        $first = true;
        foreach ($menuarr as &$menuitem) {
            $this->expand_menu_item($menuitem, $backend, $first);
            $first = false;
        }

        return $menuarr;
    }    

    /**
     * Recursively expand menu items.
     *
     * @return array
     */
    private function expand_menu_item(&$menuitem, $backend = false, $first = false) {
        global $DB;

        
        $pagedata = $DB->get_record(static::TABLE_PAGES, array(
            'id' => $menuitem['id'],
            'hidden' => 0,
            'deleted' => 0,
        ), '*', IGNORE_MULTIPLE);

        $menuitem['title'] = $first && !$backend ? 'Home' : $pagedata->title;

        //$cmid
        $url = new \moodle_url('/mod/website/site.php', array(
            'site' => $this->data->siteid,
            'page' => $pagedata->id,
        ));
        $menuitem['url'] = $url->out(false);
        $menuitem['haschildren'] = count($menuitem['children']);

        foreach ($menuitem['children'] as &$childitem) {
            $this->expand_menu_item($childitem);
        }
    }


    public function get_id() {
        if (isset($this->data->id)) {
            return $this->data->id;
        }
        return 0;
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
                throw new \coding_exception('mod_website\block: Site is missing required data: ' . $attribute);
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
                throw new \coding_exception('mod_website\block: Site is missing required related data: ' . $attribute);
            }
        }
    }

}