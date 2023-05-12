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

use mod_website\logging;
use mod_website\forms\form_sitepage;
use mod_website\permissions;

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
    const TABLE_PERMISSIONS = 'website_permissions';

    private $data = array();
    private $permissions = array();

    public $sections = array();

    private static function required_data() {
        return array('siteid');
    }

    private static function required_related() {
        return array('cmid', 'modulecontext', 'website');
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
     * create a new page record in the db and return a page instance.
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

        logging::log('Page', $id, array(
            'event' => 'Page created'
        ));

        return $this->read($id);
    }

    /**
     * update a page record in the db and return the id.
     *
     * @param $data
     * @return static
     */
    public function update() {
        global $DB;

        if ($this->data->id) {
            $this->validate_data();
            $DB->update_record(static::TABLE, $this->data);

            logging::log('Page', $this->data->id, array(
                'event' => 'Page updated'
            ));
        }
        
        return $this->data->id;
    }

    /**
     * update a page record in the db and return a page instance.
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
            $this->data->hidden = $data->hidden;
            $this->data->timemodified = time();
            $this->update();
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
     * Create an instance of this class.
     *
     * @param int $id If set, this is the id of an existing record, used to load the data.
     */
    public function read_for_site($siteid, $pageid, $includedeleted = false) {
        global $DB;

        $conditions = array('id' => $pageid, 'siteid' => $siteid, 'deleted' => 0);
        if ($includedeleted) {
            unset($conditions['deleted']);
        }
        $this->data = $DB->get_record(static::TABLE, $conditions, '*', IGNORE_MULTIPLE);
        $this->read_sections();

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
        $this->read_sections();

        return $this;
    }

    /**
     * Soft delete the page.
     *
     * @param $id
     * @return static
     */
    final public function delete($id = 0) {
        global $DB;

        if (!empty($id)) {
            $this->read($id);
        }

        if (empty($this->data->id)) {
            return;
        }

        if ( ! $this->data->siteid ) {
            return;
        }

        $site = new \mod_website\site($this->data->siteid);
        if ($site->homepageid == $this->data->id) {
            return 'Cannot delete site homepage.';
        }
        // Only a site editor can delete pages. This prevents users deleting their own page in per page distribution.
        if (!$site->can_user_edit()) {
            return 'Cannot delete page.';
        }

        $this->data->deleted = 1;
        $this->data->timemodified = time();
        $this->update();

        logging::log('Page', $this->data->id, array(
            'event' => 'Page deleted'
        ));
    }

    public function restore() {
        if (empty($this->get_id())) {
            return false;
        }
        
        // Make sure user can perform this action.
        if ( ! $this->can_user_edit()) {
            return false;
        }

        $this->data->deleted = 0;
        $this->data->timemodified = time();
        $this->update();

        logging::log('Page', $this->get_id(), array(
            'event' => 'Page restored'
        ));

        return true;
    }

    /**
     * Construct the site options.
     *
     * @return static
     */
    final public function read_sections() {
        global $DB;

        $this->sections = array();

        if ( empty($this->data->sections) ) {
            return array();
        }

        $sectionids = json_decode($this->data->sections);

        if (!empty($sectionids) ) {
            foreach ($sectionids as $id) {
                if (intval($id)) {
                    $section = new \mod_website\section($id);
                    if ( $section->get_id() ) {
                        $this->sections[] = new \mod_website\section($id);
                    }
                }
            }
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
                'website' => $related['website'],
            ));
        }

        // Attachments.
        $bannerimage = $this->export_bannerimage($related);

        return array(
            'id' => $this->get_id(),
            'title' => $this->get_title(),
            'hidden' => $this->get_hidden(),
            'bannerimage' => $bannerimage,
            'sections' => $sections,
            'numsections' => count($sections),
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
	            	'formattedfilename' => format_text($filename, FORMAT_HTML, array('context'=>$related['modulecontext'])),
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

    public function set($property, $value) {
        $this->data->$property = $value;
    }
    
    public function get_id() {
        return isset($this->data->id) ? $this->data->id : null;
    }
  
    public function get_title() {
        return isset($this->data->title) ? $this->data->title : null;
    }
  
    public function get_hidden() {
        return isset($this->data->hidden) ? $this->data->hidden : null;
    }

    public function get_siteid() {
        return isset($this->data->siteid) ? $this->data->siteid : 0;
    }

    public function get_pageurl() {
        if (!isset($this->data->id)) {
            return '';
        }
        if (!isset($this->data->siteid)) {
            return '';
        }
        $pageurl = new \moodle_url('/mod/website/site.php', array(
            'site' => $this->data->siteid,
            'page' => $this->data->id,
        ));
        return $pageurl;
    }

    public function toggle_hide($visibility) {
        if ( ! $this->data->siteid ) {
            throw new \coding_exception('Toggle hide: siteid is missing.');
        }
        $site = new \mod_website\site($this->data->siteid);
        if ($site->homepageid == $this->data->id) {
            return 'Cannot hide site homepage.';
        }
        $this->data->hidden = $visibility;
        $this->update();
        return 'success';
    }

    public function get_sections() {
        $sections = json_decode($this->data->sections);
        return $sections ? $sections : array();
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
        if ( empty($sections) || !in_array($sectionid, $sections)) {
            $sections[] = $sectionid;
            $this->data->sections = json_encode($sections);
            $DB->update_record(static::TABLE, $this->data);
        }
    }

    public function has_section($sectionid) {
        global $DB;

        if (empty($sectionid)) {
            return false;
        }
        
        if ( ! $this->data->id ) {
            return false;
        }

        $sections = $this->get_sections();
        if ( empty($sections) ) {
            return false;
        }

        if ( in_array($sectionid, $sections)) {
            return true;
        }

        return false;
    }

    public function sync_permission_selections($data) {
        if (empty($this->get_id())) {
            return;
        }
        // Can only share a site if the distribution is single site.
        //$website = $this->get_website();
        //if ($website->distribution !== '0') {
        //    return;
        //}
        permissions::sync_permission_selections('Page', $this->get_id(), $data);
    }

    public function load_editors() {
        global $DB; 
        
        if (empty($this->get_id())) {
            return;
        }

        // Permissions.
        $this->permissions = $DB->get_records(static::TABLE_PERMISSIONS, array(
            'resourcetype' => 'Page',
            'resourcekey' => $this->get_id(),
        ));
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

    public function can_user_edit() {
        global $USER, $DB;

        // Site permissions first.
        $site = new \mod_website\site($this->get_siteid());
        if ($site->can_user_edit()) {
            return true;
        }

        // Check permissions for page.
        $permissions = $DB->get_record(static::TABLE_PERMISSIONS, array(
            'permissiontype' => 'Edit',
            'resourcetype' => 'Page',
            'resourcekey' => $this->get_id(),
            'userid' => $USER->id,
        ), '*', IGNORE_MULTIPLE);
        if ($permissions) {
            return true;
        }

        return false;
    }

    public function can_user_view() {
        global $USER, $DB;

        // If page is not specified then return true so that site can load a default page.
        if (empty($this->get_id())) {
            return true;
        }

        // If this is an editor, they can always view the page.
        if ($this->can_user_edit()) {
            return true;
        }

        // If the page is hidden, then the user cannot view this page.
        if ($this->get_hidden()) {
            return false;
        }

        // If this is page-per-student distribution, staff and students can view all pages but parents can
        // only view their childs page.
        $site = new \mod_website\site($this->get_siteid());
        $website = new \mod_website\website($site->get_websiteid());
        if ($website->get_distribution() == '2') {
            // Is staff?
            if (utils::is_grader()) {
                return true;
            }
            // Is student?
            $students = utils::get_students_from_groups($website->get_groups(), $website->get_course());
            if (in_array($USER->id, $students)) {
                return true;
            }
            // Is mentor?
            if (utils::is_user_mentor_of_students($USER->id, $students)) {
                // Is this their child's page? Child has edit access.
                $mentees = utils::get_users_mentees($USER->id, 'id');
                foreach($mentees as $mentee) {
                    $permissions = $DB->get_record(static::TABLE_PERMISSIONS, array(
                        'permissiontype' => 'Edit',
                        'resourcetype' => 'Page',
                        'resourcekey' => $this->get_id(),
                        'userid' => $mentee,
                    ), '*', IGNORE_MULTIPLE);
                    if ($permissions) {
                        return true;
                    }
                }
            }
        }

        return false;
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