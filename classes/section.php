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
class Section {

    /** The table name. */
    const TABLE = 'website_site_sections';
    const TABLE_SITES = 'website_sites';
    const TABLE_PAGES = 'website_site_pages';

    private $data = array();

    public $blocks = null;

    private static function required_data() {
        return array('siteid', 'layout');
    }

    private static function required_related() {
        return array('cmid', 'pageid', 'modulecontext', 'website');
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

        $this->data = (object) $data;
        $this->data->timecreated = time();
        $this->data->timemodified = time();

        $this->validate_data();
        
        $id = $DB->insert_record(static::TABLE, $this->data);

        return $this->read($id);
    }

    /**
     * create a section record in the db and return the id.
     *
     * @param $data
     * @return static
     */
    public function save($data) {
        global $DB;

        if (empty($data->id)) {
            // Create new
            $this->create($data);
        } else {
            //Read and update existing.
            $this->read($data->id);
            $this->data->title = $data->title;
            $this->data->layout = $data->layout;
            $this->data->sectionoptions = $data->sectionoptions;
            $this->data->timemodified = time();
            $this->data->hidden = $data->hidden;
            $this->validate_data();
            $DB->update_record(static::TABLE, $this->data);
        }
        
        return $this->data->id;
    }

    /**
     * create a section record in the db and return the id.
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
     * Load the data from the DB.
     *
     * @param $id
     * @return static
     */
    final public function read($id) {
        global $DB;

        $this->data = $DB->get_record(static::TABLE, array('id' => $id), '*', IGNORE_MULTIPLE);
        $this->read_blocks();

        return $this;
    }

    /**
     * Get the section blocks.
     *
     * @return static
     */
    final public function read_blocks() {
        global $DB;
        
        if ( !$this->data->layout) {
            return;
        }

        $blockids = json_decode($this->data->blocks);

        $this->blocks = array();
        foreach($blockids as $blockid) {
            if (intval($blockid)) {
                $this->blocks[] = new \mod_website\block($blockid);
            }
        }

        return $this->blocks;
    }

    /**
     * Serialise data based on related info to a structure ready for rendering.
     *
     * @return array
     */
    public function export($related) {
        $this->validate_related($related);

        $sectionurl = new \moodle_url('/mod/website/edit-section.php', array(
            'site' => $this->data->siteid,
            'page' => $related['pageid'],
            'section' => $this->data->id,
        ));
        $newblockurl = new \moodle_url('/mod/website/edit-block.php', array(
            'site' => $this->data->siteid,
            'page' => $related['pageid'],
            'section' => $this->data->id,
        ));

        $blocks = array();
        foreach ($this->blocks as $block) {
            $blocks[] = $block->export(array(
                'siteid' => $this->data->siteid,
                'pageid' => $related['pageid'],
                'sectionid' => $this->data->id,
                'modulecontext' => $related['modulecontext'],
                'website' => $related['website']
            ));
        }
        
        return array(
            'blocks' => $blocks,
            'sectionurl' => $sectionurl->out(false),
            'newblockurl' => $newblockurl->out(false),
            'title' => $this->data->title,
            'layout' => $this->data->layout,
            'id' => $this->data->id,
            'options' => json_decode($this->data->sectionoptions),
        );
    }    

    public function get_id() {
        return $this->data->id;
    }

    public function get_blocks() {
        return json_decode($this->data->blocks);
    }
    
    public function get_hidden() {
        return $this->data->hidden;
    }

    public function add_block_to_section($blockid) {
        global $DB;

        if (empty($blockid)) {
            throw new \coding_exception('Add block to section: block id is empty.');
        }
        
        if ( ! $this->data->id ) {
            throw new \coding_exception('Add block to section: section id is missing.');
        }
        $blocks = $this->get_blocks();
        if ( ! in_array($blockid, $blocks)) {
            $blocks[] = $blockid;
            $this->data->blocks = json_encode($blocks);
            $DB->update_record(static::TABLE, $this->data);
        }
    }

    public function set($property, $value) {
        $this->data->$property = $value;
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

}