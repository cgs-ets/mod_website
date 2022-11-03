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

use mod_website\forms\form_sitepage;

/**
 * Provides utility functions for this plugin.
 *
 * @package   mod_website
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class Page {

    /** The table name. */
    const TABLE = 'website_site_pages';
    const TABLE_SITES = 'website_sites';
    const TABLE_SECTIONS = 'website_site_sections';

    private $data = array();
    private $sections = null;

    private static function required_data() {
        return array('siteid');
    }

    private static function required_related() {
        return array('cmid', 'modulecontext');
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
     * create a new site record in the db and return a site instance.
     * 
     * @param $formdata
     * @return static
     */
    public function save($data, $modulecontext) {
        global $DB;

        if (empty($this->data->id)) {
            // Create a new record.
            $this->create($data);
        } else {
            //Read and update existing.
            $this->read($this->data->id);
            $this->data->title = $data->title;
            $this->data->timemodified = time();
            $this->validate_data();
            $DB->update_record(static::TABLE, $this->data);
        }

        // If there is a banner image save it.
        if ($data->bannerimage) {
            file_save_draft_area_files(
                $data->bannerimage, 
                $modulecontext->id, 
                'mod_website', 
                'bannerimage', 
                $this->data->id, 
                form_sitepage::file_options()
            );
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

        $this->data = $DB->get_record(static::TABLE, array('id' => $id, 'deleted' => 0), '*', IGNORE_MULTIPLE);
        $this->read_sections();

        return $this;
    }

    /**
     * Construct the site options.
     *
     * @return static
     */
    final public function read_sections() {
        global $DB;

        if ( empty($this->data->sections) ) {
            return;
        }

        $sectionids = json_decode($this->data->sections);

        $this->sections = array();
        foreach ($sectionids as $id) {
            $this->sections[] = new \mod_website\section($id);
        }

        return $this->sections;
    }

    /**
     * Serialise data based on related info to a structure ready for rendering.
     *
     * @return array
     */
    public function export($related) {
        $this->validate_related($related);

        $sections = array();
        foreach ($this->sections as $section) {
            $sections[] = $section->export(array(
                'pageid' => $this->data->id,
                'cmid' => $related['cmid'],
                'modulecontext' => $related['modulecontext'],
            ));
        }

        // Attachments.
        $bannerimage = $this->export_bannerimage($related);

        return array(
            'title' => $this->get_title(),
            'bannerimage' => $bannerimage,
            'sections' => $sections,
        );

    }

    private function export_bannerimage($related) {
		global $CFG;

		$bannerimages = [];
	    $fs = get_file_storage();
	    $files = $fs->get_area_files($related['modulecontext']->id, 'mod_website', 'bannerimage', $this->data->id, "filename", false);
	    if ($files) {
	    	// Sort by time added.
	    	usort($files, function($a, $b) {return strcmp($a->get_source(), $b->get_source());});
	        foreach ($files as $file) {
	            $filename = $file->get_filename();
	            $mimetype = $file->get_mimetype();
	            $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$related['modulecontext']->id.'/mod_website/bannerimage/'.$this->data->id.'/'.$filename);
	            $isimage = strpos($mimetype, 'image') !== false ? 1 : 0;
	            $isvideo = strpos($mimetype, 'video') !== false ? 1 : 0;
	            $bannerimage = [
                    'id' => $file->get_id(),
                    'postid' => $file->get_itemid(),
	            	'filename' => $filename,
	            	'formattedfilename' => format_text($filename, FORMAT_HTML, array('context'=>$this->related['context'])),
	            	'mimetype' => $mimetype,
	            	'path' => $path,
	            	'isimage' => $isimage,
	            	'isvideo' => $isvideo,
	            	'contenthash' => $file->get_contenthash(),
	            	'filepath' => $file->get_filepath(),
	            ];
	            $bannerimages[] = (object) $bannerimage;
                // Just one banner image.
                break;
	        }
	    }

	    return $bannerimages;
	}
    
    public function get_id() {
        return $this->data->id;
    }
  
    public function get_title() {
        return $this->data->title;
    }

    public function get_sections() {
        return json_decode($this->data->sections);
    }
    
    public function add_section_to_page($sectionid) {
        global $DB;

        if (empty($sectionid)) {
            throw new \coding_exception('Add section to page: section id is empty.');
        }
        
        if ( ! $this->data->id ) {
            throw new \coding_exception('Add section to page: page id is missing.');
        }
        $sections = $this->get_sections();
        if ( ! in_array($sectionid, $sections)) {
            $sections[] = $sectionid;
            $this->data->sections = json_encode($sections);
            $DB->update_record(static::TABLE, $this->data);
        }
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

    public function can_user_edit() {
        global $USER, $DB;
        $siteuser = $DB->get_field(static::TABLE_SITES, 'userid', array('id' => $this->data->siteid, 'deleted' => 0), '*', IGNORE_MULTIPLE);
        return ($siteuser === $USER->id);
    }

}