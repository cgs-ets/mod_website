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
class copying {

    const TABLE_PAGES = 'website_site_pages';
    const TABLE_SECTIONS = 'website_site_sections';
    const TABLE_BLOCKS = 'website_site_blocks';
    const TABLE_MENUS = 'website_site_menus';

    public static function create($resourcetype, $resourcekey, $userid) {
        global $DB, $USER;

        
        return true;
    }


    public static function copy_menus($fromsiteid, $tositeid) {
        global $DB;
        $copies = array(0 => 0);
        $menus = $DB->get_records(static::TABLE_MENUS, array(
            'siteid' => $fromsiteid,
        ));
        foreach ($menus as $menu) {
            // Keep track of the old id.
            $oldid = $menu->id;
            unset($menu->id);
            // Update the data.
            $menu->siteid = $tositeid;
            $newmenu = new \mod_website\menu();
            $newmenu = $newmenu->create($menu);
            $copies[$oldid] = intval($newmenu->get_id());
        }
        return $copies;
    }

    public static function copy_pages($fromsiteid, $tositeid) {
        global $DB;
        $copies = array(0 => 0);
        $pages = $DB->get_records(static::TABLE_PAGES, array(
            'siteid' => $fromsiteid,
            'deleted' => 0,
        ));
        foreach ($pages as $page) {
            // Keep track of the old id.
            $oldid = $page->id;
            unset($page->id);
            // Update the data.
            $page->siteid = $tositeid;
            $newpage = new \mod_website\page();
            $newpage = $newpage->create($page);
            $copies[$oldid] = intval($newpage->get_id());
        }
        return $copies;
    }

    public static function copy_sections($fromsiteid, $tositeid) {
        global $DB;
        $copies = array(0 => 0);
        $sections = $DB->get_records(static::TABLE_SECTIONS, array(
            'siteid' => $fromsiteid,
            'deleted' => 0,
        ));
        foreach ($sections as $section) {
            // Keep track of the old id.
            $oldid = $section->id;
            unset($section->id);
            // Update the data.
            $section->siteid = $tositeid;
            $newsection = new \mod_website\section();
            $newsection = $newsection->create($section);
            $copies[$oldid] = intval($newsection->get_id());
        }
        return $copies;
    }

    public static function copy_blocks($fromsiteid, $tositeid) {
        global $DB;
        $copies = array(0 => 0);
        $blocks = $DB->get_records(static::TABLE_BLOCKS, array(
            'siteid' => $fromsiteid,
            'deleted' => 0,
        ));
        foreach ($blocks as $block) {
            // Keep track of the old id.
            $oldid = $block->id;
            unset($block->id);
            // Update the data.
            $block->siteid = $tositeid;
            $newblock = new \mod_website\block();
            $newblock = $newblock->create($block);
            $newblock->set('content', $block->content);
            $newblock->update();
            $copies[$oldid] = intval($newblock->get_id());
        }
        return $copies;
    }

    /* This is used to copy a single page based on a provided template URL. Used when creating page per student distribution */
    public static function copy_page($copypageid, $tositeid) {
        global $DB;
        $copies = array();
        $pagedata = $DB->get_record(static::TABLE_PAGES, array(
            'id' => $copypageid,
            'deleted' => 0,
        ));
        unset($pagedata->id);
        // Update the data.
        $pagedata->siteid = $tositeid;
        $newpage = new \mod_website\page();
        $newpage = $newpage->create($pagedata);
        return intval($newpage->get_id());
    }

    /* This is used to copy a single page based on a provided template URL. Used when creating page per student distribution */
    public static function copy_page_sections($frompageid, $tositeid) {
        global $DB;
        $copies = array(0 => 0);
        $page = new \mod_website\page($frompageid); 
        foreach ($page->sections as $section) {
            // Keep track of the old id.
            $oldid = $section->get_id();
            // Update the data.
            $section->set_siteid($tositeid);
            $newsection = $section->save_as();
            $copies[$oldid] = intval($newsection->get_id());
        }
        return $copies;
    }

    /* This is used to copy a single page based on a provided template URL. Used when creating page per student distribution */
    public static function copy_page_blocks($frompageid, $tositeid) {
        global $DB;
        $copies = array(0 => 0);
        $page = new \mod_website\page($frompageid); 
        foreach ($page->sections as $section) {
            foreach ($section->blocks as $block) {
                // Keep track of the old id.
                $oldid = $block->get_id();
                // Update the data.
                $block->set_siteid($tositeid);
                $newblock = $block->save_as();
                $copies[$oldid] = intval($newblock->get_id());
            }
        }
        return $copies;
    }

    /* This is used for the copy section feature */
    public static function copy_section($copysectionid, $tositeid = 0) {
        global $DB;
        $copies = array();
        $sectiondata = $DB->get_record(static::TABLE_SECTIONS, array(
            'id' => $copysectionid,
            'deleted' => 0,
        ));
        unset($sectiondata->id);
        // Update the data.
        if ($tositeid) {
            $sectiondata->siteid = $tositeid;
        }
        $newsection = new \mod_website\section();
        $newsection = $newsection->create($sectiondata);
        return intval($newsection->get_id());
    }

    /* This is used for the copy section feature */
    public static function clone_section_into_page($siteid, $copysectionid, $destpageid) {
        global $DB;
        $blockcopies = array(0 => 0);
        $site = new \mod_website\site($siteid); 
        $section = new \mod_website\section($copysectionid); 
        $cmcontext = \context_module::instance($site->get_cmid());
        $fs = get_file_storage();

        // Copy the section record.
        $newsectionid = static::copy_section($copysectionid);

        // Copy the block records.
        foreach ($section->blocks as $block) {
            $oldid = $block->get_id();
            $newblock = $block->save_as();
            $blockcopies[$oldid] = intval($newblock->get_id());
        }

        static::copy_block_files($blockcopies, $cmcontext, $cmcontext);

        // Replace blocks in new section.
        $newsection = new \mod_website\section($newsectionid);
        $blocks = $newsection->get_blocks();
        foreach($blocks as &$block) {
            $block = $blockcopies[$block];
        }
        $newsection->set('blocks', json_encode($blocks));
        $newsection->update();
 
        // Add section to page.
        $page = new \mod_website\page($destpageid);  
        $page->add_section_to_page($newsectionid);

        return true;
    }

    public static function copy_page_files($pagecopies, $oldcontext, $newcontext) {
        $fs = get_file_storage();
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
    }

    public static function copy_block_files($blockcopies, $oldcontext, $newcontext) {
        $fs = get_file_storage();
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

    }

    public static function update_content_links($pagecopies, $blockcopies, $oldsiteid, $newsiteid) {
        foreach ($blockcopies as $blockid) {
            if ($blockid == 0) { continue; }
            $block = new \mod_website\block($blockid);
            $content = $block->get_content();
            
            // Replace site param.
            $content = str_replace('site=' . $oldsiteid, 'site=' . $newsiteid, $content);

            // Replace page params.
            foreach ($pagecopies as $oldpageid => $newpageid) {
                $content = str_replace('page=' . $oldpageid, 'page=' . $newpageid, $content);
            }

            $block->set('content', $content);
            $block->update();
        }
    }

    public static function update_menu_page_references($menucopies, $pagecopies) {
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
    }
   
    public static function update_page_section_references($pagecopies, $sectioncopies) {
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
    }

    public static function update_section_block_references($sectioncopies, $blockcopies) {
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
    }

}