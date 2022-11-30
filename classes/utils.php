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

use mod_website\website;

/**
 * Provides utility functions for this plugin.
 *
 * @package   mod_website
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class utils {


    public static function is_grader() {
        global $COURSE;

        $context = \context_course::instance($COURSE->id);
        if (has_capability('moodle/grade:manage', $context)) {
            return true;
        }
        return false;
    }

    public static function is_user_mentor_of_student($mentoruserid, $studentuserid) {
        $mentors = static::get_users_mentors($studentuserid, 'id');

        return in_array($mentoruserid, $mentors);
    }

    public static function get_users_mentors($userid, $field = 'username') {
        global $DB;

        $mentors = array();
        $mentorssql = "SELECT u.*
                         FROM {role_assignments} ra, {context} c, {user} u
                        WHERE c.instanceid = :menteeid
                          AND c.contextlevel = :contextlevel
                          AND ra.contextid = c.id
                          AND u.id = ra.userid";
        $mentorsparams = array(
            'menteeid' => $userid,
            'contextlevel' => CONTEXT_USER
        );
        if ($mentors = $DB->get_records_sql($mentorssql, $mentorsparams)) {
            $mentors = array_column($mentors, $field);
        }
        return $mentors;
    }

    public static function get_users_mentees($userid, $field = 'username') {
        global $DB;

        // Get mentees for user.
        $mentees = array();
        $menteessql = "SELECT u.*
                         FROM {role_assignments} ra, {context} c, {user} u
                        WHERE ra.userid = :mentorid
                          AND ra.contextid = c.id
                          AND c.instanceid = u.id
                          AND c.contextlevel = :contextlevel";     
        $menteesparams = array(
            'mentorid' => $userid,
            'contextlevel' => CONTEXT_USER
        );
        if ($mentees = $DB->get_records_sql($menteessql, $menteesparams)) {
            $mentees = array_column($mentees, $field);
        }
        return $mentees;
    }

    // NOT USED - Implemented own cleaner.
    public static function should_clean_content($website) {
        // Don't clean teacher sites.
        if ($website->distribution === '0') {
            return true; //noclean
        }
        // Clean student sites.
        return false;
    }

    /**
     * Helper function to get the students enrolled
     *
     * @param int $courseid
     * @return int[]
     */
    public static function get_enrolled_students($courseid) {
        global $DB;
        $context = \context_course::instance($courseid);
        
        // 5 is student.
        $studentroleid = $DB->get_field('role', 'id', array('shortname'=> 'student'));
        $users = get_role_users($studentroleid, $context, false, 'u.id, u.username, u.firstname, u.lastname', 'u.lastname'); //last param is sort by.

        return array_map('intval', array_column($users, 'id'));
    }


    public static function get_students_from_groups($groups, $courseid) {
        $students = array();
        if (! in_array('00_everyone', $groups) )
        {
            // Specific groups or groupings.
            foreach ( $groups as $groupselection ) {
                $split = explode('_', $groupselection);
                if (intval($split[0]) === 0) {
                    continue;
                }
                if ($split[1] === 'group') {
                    $students = array_merge($students, array_column(groups_get_members($split[0], 'u.id'), 'id'));
                }
                if ($split[1] === 'grouping') {
                    $students = array_merge($students, array_column(groups_get_grouping_members($split[0], 'u.id')));
                }
            }
        }
        else
        {
            // Everyone - Get all students in course.
            $students = static::get_enrolled_students($courseid);
        }
        return $students;
    }

    public static function sync_student_sites($websiteid, $groups, $courseid, $cmid, $creatorid, $newname) {
        $newstudents = static::get_students_from_groups($groups, $courseid);
        $website = new Website($websiteid, $cmid);
        $website->load_sites();
        foreach ($website->get_sites() as $site) {
            if ( $i = array_search($site->get_userid(), $newstudents) ) {
                unset($newstudents[$i]);
                continue;
            } else {
                //$site->delete();
            }
        }
        // Create the left over.
        $website->create_sites_for_students($newstudents, array(
            'websiteid' => $websiteid,
            'cmid' => $cmid,
            'creatorid' => $creatorid,
            'name' => $newname,
        ));
    }


    // Moodle's purification is too restrictive. At the same time, we don't want to turn it off.
    // This is a modified version of weblib.php line 1776.
    // https://github.com/moodle/moodle/blob/master/lib/weblib.php
    public static function purify_html($text) {
        global $CFG;
    
        $text = (string)$text;
    
        static $purifiers = array();
        static $caches = array();
    
        // Purifier code can change only during major version upgrade.
        $version = empty($CFG->version) ? 0 : $CFG->version;
        $cachedir = "$CFG->localcachedir/htmlpurifier/$version";
        if (!file_exists($cachedir)) {
            // Purging of caches may remove the cache dir at any time,
            // luckily file_exists() results should be cached for all existing directories.
            $purifiers = array();
            $caches = array();
            gc_collect_cycles();
    
            make_localcache_directory('htmlpurifier', false);
            check_dir_exists($cachedir);
        }
    
        // MIKEV - MODIFIED FOR WEBSITES. 
        $allowid = 1;
        $allowobjectembed = 1;
    
        $type = 'type_'.$allowid.'_'.$allowobjectembed;
    
        if (!array_key_exists($type, $caches)) {
            $caches[$type] = \cache::make('core', 'htmlpurifier', array('type' => $type));
        }
        $cache = $caches[$type];

        // MIKEV - Add a code version to the cache key.
        $localversion = '2022113002';
        // Add revision number and all options to the text key so that it is compatible with local cluster node caches.
        $key = "|$version|$allowobjectembed|$allowid|$text|$localversion";
        $filteredtext = $cache->get($key);
    
        if ($filteredtext === true) {
            // The filtering did not change the text last time, no need to filter anything again.
            return $text;
        } else if ($filteredtext !== false) {
            return $filteredtext;
        }
    
        if (empty($purifiers[$type])) {
            require_once $CFG->libdir.'/htmlpurifier/HTMLPurifier.safe-includes.php';
            require_once $CFG->libdir.'/htmlpurifier/locallib.php';
            $config = \HTMLPurifier_Config::createDefault();
    
            $config->set('HTML.DefinitionID', 'moodlehtml');
            $config->set('HTML.DefinitionRev', 6);
            $config->set('Cache.SerializerPath', $cachedir);
            $config->set('Cache.SerializerPermissions', $CFG->directorypermissions);
            $config->set('Core.NormalizeNewlines', false);
            $config->set('Core.ConvertDocumentToFragment', true);
            $config->set('Core.Encoding', 'UTF-8');
            $config->set('HTML.Doctype', 'XHTML 1.0 Transitional');
            $config->set('URI.AllowedSchemes', array(
                'http' => true,
                'https' => true,
                'ftp' => true,
                'irc' => true,
                'nntp' => true,
                'news' => true,
                'rtsp' => true,
                'rtmp' => true,
                'teamspeak' => true,
                'gopher' => true,
                'mms' => true,
                'mailto' => true
            ));
            $config->set('Attr.AllowedFrameTargets', array('_blank'));
    
            if ($allowobjectembed) {
                $config->set('HTML.SafeObject', true);
                $config->set('Output.FlashCompat', true);
                $config->set('HTML.SafeEmbed', true);
                
                // MIKEV - ADDED FOR WEBSITES. 
                // Allow iframes from trusted sources
                $config->set('HTML.SafeIframe', true);
                $config->set('URI.SafeIframeRegexp', '%^(https?:)?//%'); //allow all embeds
                //$cfg->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%'); //allow YouTube and Vimeo
            }
    
            if ($allowid) {
                $config->set('Attr.EnableID', true);
            }
    
            if ($def = $config->maybeGetRawHTMLDefinition()) {
                $def->addElement('nolink', 'Inline', 'Flow', array());                      // Skip our filters inside.
                $def->addElement('tex', 'Inline', 'Inline', array());                       // Tex syntax, equivalent to $$xx$$.
                $def->addElement('algebra', 'Inline', 'Inline', array());                   // Algebra syntax, equivalent to @@xx@@.
                $def->addElement('lang', 'Block', 'Flow', array(), array('lang'=>'CDATA')); // Original multilang style - only our hacked lang attribute.
                $def->addAttribute('span', 'xxxlang', 'CDATA');                             // Current very problematic multilang.
    
                // Media elements.
                // https://html.spec.whatwg.org/#the-video-element
                $def->addElement('video', 'Block', 'Optional: #PCDATA | Flow | source | track', 'Common', [
                    'src' => 'URI',
                    'crossorigin' => 'Enum#anonymous,use-credentials',
                    'poster' => 'URI',
                    'preload' => 'Enum#auto,metadata,none',
                    'autoplay' => 'Bool',
                    'playsinline' => 'Bool',
                    'loop' => 'Bool',
                    'muted' => 'Bool',
                    'controls' => 'Bool',
                    'width' => 'Length',
                    'height' => 'Length',
                ]);
                // https://html.spec.whatwg.org/#the-audio-element
                $def->addElement('audio', 'Block', 'Optional: #PCDATA | Flow | source | track', 'Common', [
                    'src' => 'URI',
                    'crossorigin' => 'Enum#anonymous,use-credentials',
                    'preload' => 'Enum#auto,metadata,none',
                    'autoplay' => 'Bool',
                    'loop' => 'Bool',
                    'muted' => 'Bool',
                    'controls' => 'Bool'
                ]);
                // https://html.spec.whatwg.org/#the-source-element
                $def->addElement('source', false, 'Empty', null, [
                    'src' => 'URI',
                    'type' => 'Text'
                ]);
                // https://html.spec.whatwg.org/#the-track-element
                $def->addElement('track', false, 'Empty', null, [
                    'src' => 'URI',
                    'kind' => 'Enum#subtitles,captions,descriptions,chapters,metadata',
                    'srclang' => 'Text',
                    'label' => 'Text',
                    'default' => 'Bool',
                ]);
    
                // Use the built-in Ruby module to add annotation support.
                $def->manager->addModule(new \HTMLPurifier_HTMLModule_Ruby());
            }
    
            $purifier = new \HTMLPurifier($config);
            $purifiers[$type] = $purifier;
        } else {
            $purifier = $purifiers[$type];
        }
    
        $multilang = (strpos($text, 'class="multilang"') !== false);
    
        $filteredtext = $text;
        if ($multilang) {
            $filteredtextregex = '/<span(\s+lang="([a-zA-Z0-9_-]+)"|\s+class="multilang"){2}\s*>/';
            $filteredtext = preg_replace($filteredtextregex, '<span xxxlang="${2}">', $filteredtext);
        }
        $filteredtext = (string)$purifier->purify($filteredtext);
        if ($multilang) {
            $filteredtext = preg_replace('/<span xxxlang="([a-zA-Z0-9_-]+)">/', '<span lang="${1}" class="multilang">', $filteredtext);
        }
    
        if ($text === $filteredtext) {
            // No need to store the filtered text, next time we will just return unfiltered text
            // because it was not changed by purifying.
            $cache->set($key, true);
        } else {
            $cache->set($key, $filteredtext);
        }
    
        return $filteredtext;
    }
}