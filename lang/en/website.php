<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     mod_website
 * @category    string
 * @copyright   2022 Michael Vangelovski <michael.vangelovski@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Website';
$string['pluginname_desc'] = 'Create a course website without code';
$string['websitename'] = 'Website name';
$string['websitenameplural'] = 'Websites';
$string['modulenameplural'] = 'Websites';
$string['modulename'] = 'Website';
$string['pluginadministration'] = 'Website administration';
$string['websitename_help'] = 'Create a course website without code';
$string['websitesettings'] = 'Website Settings';
$string['websitefieldset'] = 'Website Fieldset';
$string['website:addinstance'] = 'Add a new website instance';

$string['distribution'] = 'Distribution';
$string['pagetitle'] = 'Page title';
$string['editpage'] = 'Edit page';
$string['addtomenu'] = 'Site menu';
$string['visibility'] = 'Visibility';
$string['visible'] = 'Visible';
$string['privatepage'] = 'Private (only you will be able to access this page)';
$string['privatesection'] = 'Private (only you will be able to see this section)';
$string['privateblock'] = 'Private (only you will be able to see this block)';
$string['bannerimage'] = 'Banner image';
$string['alloweditingfromdate'] = 'Allow editing from';
$string['alloweditingfromdate_help'] = 'If enabled, students will not be able to edit their website before this date. If disabled, students will be able to start editing right away.';
$string['cutoffdate'] = 'Editing cut-off date';
$string['cutoffdate_help'] = 'If set, the website will not be editable after this date.';

$string['template'] = 'Template';
$string['useexistingurl'] = 'Template site URL';
$string['useexistingurl_help'] = 'Copy and paste the URL of an existing site to use it as a template.';

$string['nopermissiontoview'] = 'You do not have permission to view this website.';
$string['nopermissiontoedit'] = 'You do not have permission to edit this website.';

$string['editmenu'] = 'Edit menu';
$string['editsection'] = 'Edit section';
$string['sectionlayout'] = 'Section layout';
$string['sectiontitle'] = 'Section title';
$string['hidetitle'] = 'Hide title?';
$string['collapsible'] = 'Section collapsible?';

$string['editblock'] = 'Edit block';
$string['blocktype'] = 'Block type';
$string['wysiwyg'] = 'Content editor';
$string['button'] = 'Button';
$string['picturebutton'] = 'Picture button';

$string['buttontitle'] = 'Button caption';
$string['buttonlinktype'] = 'What would you like to link to?';
$string['buttonfile'] = 'File';
$string['buttonurl'] = 'URL';
$string['uploadfile'] = 'Upload file';
$string['includepicture'] = 'Would you like a picture for your button?';
$string['buttonpicture'] = 'Button picture';
$string['buttonlinkedfile'] = 'Button file';

$string['studentnameheader'] = 'Student';
$string['websitelinkheader'] = 'Website link';
$string['previewheader'] = 'Preview';
$string['launch'] = 'Launch website';
$string['gradeheader'] = 'Grade';
$string['grade'] = 'Grade';
$string['viewgrading'] = 'View all submissions';
$string['savingchanges'] = 'Saving changes';
$string['nousersselected'] = 'No users selected';

$string['groups'] = 'Groups';
$string['groupings'] = '---- GROUPINGS ----';
$string['groupsoptionheading'] = '---- GROUPS ----';
$string['groupheader'] = 'Groups';
$string['group_select'] = 'Groups  Selection';
$string['description'] = 'Description';
$string['group_select_help'] = '<strong>Everyone</strong>: All Students enrolled in the course. <br> <strong>Groups</strong>: Students belonging to any of the available groups. <br>'
    . '<strong>Groupings</strong>: Groups belonging to any of the groupings in the course.';
$string['everyone'] = 'Everyone';
$string['all_groups'] = 'All Groups';
$string['all_groupings'] = 'All Groupings';
$string['groupsinvalidselection'] = 'Please select group value(s)';
$string['groupingsinvalid'] = 'You have chosen All Groupings and a particular grouping. Either select All Groupings or the grouping you want to share a file with.';
$string['groupingsinvalidselection'] = 'Please Select Grouping value(s)';
$string['std_invalid_selection'] = 'The selected combination is invalid.';
$string['groupsgroupingsheader'] = 'Groups - Groupings';

$string['sharing'] = 'Sharing';
$string['distsinglesharing'] = '<p class="alert alert-warning">By default this website is editable by you only. <br> You may allow other people to edit by selecting users, groups, or roles below.</p>';
$string['distmultisharing'] = '<p class="alert alert-warning">This feature only applies to single site ditributions.</p>';
$string['roles'] = 'Roles';
$string['users'] = 'Users';
