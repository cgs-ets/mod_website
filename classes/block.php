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
class Block {

    /** The table name. */
    const TABLE = 'website_site_blocks';
    const TABLE_SITES = 'website_sites';
    const TABLE_PAGES = 'website_site_pages';
    const TABLE_SECTIONS = 'website_site_sections';

    private $data = array();

    private static function required_data() {
        return array('siteid', 'type');
    }

    private static function required_related() {
        return array('sectionid', 'modulecontext', 'website');
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
     * create a new block record in the db and return a block instance.
     *
     * @param $data
     * @return static
     */
    public function create($data) {
        global $DB;

        $this->data = clone($data);
        $this->data = (object) $this->data;
        $this->data->timecreated = time();
        $this->data->timemodified = time();
        $this->data->content = '';

        $this->validate_data();

        $id = $DB->insert_record(static::TABLE, $this->data);

        return $this->read($id);
    }

    /**
     * create a block record in the db and return the id.
     *
     * @param $data
     * @return static
     */
    public function save($data, $modulecontext) {
        global $DB;

        if (empty($data->id)) {
            // Create new
            $data->id = $this->create($data)->data->id;
        }

        //Read and update existing.
        $this->read($data->id);
        $this->data->timemodified = time();
        $this->data->type = $data->type;
        $this->data->hidden = $data->hidden;
        $this->validate_data();
        
        if ($data->type == 'editor') {
            // Store content files to permanent file area and get text.
            $this->data->content = file_save_draft_area_files(
                $data->content['itemid'], 
                $modulecontext->id, 
                'mod_website', 
                'content', 
                $this->data->id,
                form_siteblock::editor_options(), 
                $data->content['text'],
            );
        }

        if ($data->type == 'picturebutton') {
            $this->data->content = json_encode(array(
                'buttontitle' => $data->buttontitle,
                'linktype' => $data->buttonlinktypegroup['buttonlinktype'],
                'buttonurl' => $data->buttonurl,
                'includepicture' => $data->includepicturegroup['includepicture'],
            ));
            // Save the file.
            if ($data->buttonfile) {
                file_save_draft_area_files(
                    $data->buttonfile, 
                    $modulecontext->id, 
                    'mod_website', 
                    'buttonfile', 
                    $this->data->id, 
                    form_siteblock::file_options()
                );
            }
            // Save the picture.
            if ($data->buttonpicture) {
                file_save_draft_area_files(
                    $data->buttonpicture, 
                    $modulecontext->id, 
                    'mod_website', 
                    'picturebutton', 
                    $this->data->id, 
                    form_siteblock::picture_options()
                );
            }
        }
        
        $DB->update_record(static::TABLE, $this->data);
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

        return $this;
    }

    /**
     * Soft delete the block.
     *
     * @param $id
     * @return static
     */
    final public function delete($id = 0) {
        global $DB;

        if ( ! empty($id)) {
            $this->read($id);
        }

        if (empty($this->data->id)) {
            return;
        }

        $this->data->deleted = 1;
        $this->update();
    }

    /**
     * update block data.
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
     * Serialise data based on related info to a structure ready for rendering.
     *
     * @return array
     */
    public function export($related) {
        global $OUTPUT;

        $this->validate_related($related);

        $blockurl = new \moodle_url('/mod/website/edit-block.php', array(
            'site' => $this->data->siteid,
            'page' => $related['pageid'],
            'section' => $related['sectionid'],
            'block' => $this->get_id() ? $this->get_id() : 0,
        ));

        // Generate the block html.
        $html = '';
        if ($this->data->type == 'editor' || empty($this->data->type)) {
            if ($this->get_id()) {
                $html = file_rewrite_pluginfile_urls(
                    $this->data->content, 
                    'pluginfile.php', 
                    $related['modulecontext']->id,
                    'mod_website', 
                    'content', 
                    $this->get_id()
                );
                // Apply filters
                $options = (object) array(
                    'context' => $related['modulecontext'],
                    'noclean' => utils::should_clean_content($related['website']), // We may want this cleaned for security (student site)
                    'nocache' => true,
                );
                $html = format_text($html, FORMAT_HTML, $options);
            }
        }

        if ($this->data->type == 'picturebutton') {
            if ($this->get_id()) {
                $image = $this->export_buttonpicture($related);
                $fileurl = $this->get_buttonfileurl($related);
                $settings = json_decode($this->data->content);
                $buttondata = array(
                    'buttontitle' => $settings->buttontitle,
                    'isfile' => $settings->linktype == 'file',
                    'isurl' => $settings->linktype == 'url',
                    'buttonurl' => $settings->buttonurl,
                    'buttonfile' => $fileurl,
                    'includepicture' => $settings->includepicture,
                    'buttonpicture' => $image,
                );
                $html = $OUTPUT->render_from_template('mod_website/_block_picturebutton', $buttondata);
            }
        }

        return array(
            'blockid' => $this->get_id(),
            'hidden' => $this->get_hidden(),
            'html' => $html,
            'blockurl' => $blockurl->out(false),
        );
    }

    private function export_buttonpicture($related) {
		global $CFG;

		$image = null;
        $plugin = 'mod_website';
        $filearea = 'picturebutton';
	    $fs = get_file_storage();
	    $files = $fs->get_area_files($related['modulecontext']->id, $plugin, $filearea, $this->data->id, "filename", false);
        if (count($files)) {
            // Get first file. Should only be one.
            $file = reset($files);
            $filename = $file->get_filename();
            $mimetype = $file->get_mimetype();
            $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$related['modulecontext']->id.'/'.$plugin.'/'.$filearea.'/'.$this->data->id.'/'.$filename);
            $isimage = strpos($mimetype, 'image') !== false ? 1 : 0;
            $isvideo = strpos($mimetype, 'video') !== false ? 1 : 0;
            $image = (object) array(
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
            );
        }
	    return $image;
	}


    private function get_buttonfileurl($related) {
		global $CFG;

		$url = '';
        $plugin = 'mod_website';
        $filearea = 'buttonfile';
	    $fs = get_file_storage();
	    $files = $fs->get_area_files($related['modulecontext']->id, $plugin, $filearea, $this->data->id, "filename", false);
        if (count($files)) {
            // Get first file. Should only be one.
            $file = reset($files);
            $filename = $file->get_filename();
            $url = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$related['modulecontext']->id.'/'.$plugin.'/'.$filearea.'/'.$this->data->id.'/'.$filename);
        }
	    return $url;
	}


    public function get_id() {
        if (isset($this->data->id)) {
            return $this->data->id;
        }
    }
    
    public function get_hidden() {
        return $this->data->hidden;
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