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
$string['sectionlayout'] = 'Layout';
$string['sectiontitle'] = 'Section title';
$string['hidetitle'] = 'Hide title?';
$string['titlehidden'] = 'Do not display title';
$string['titlevisible'] = 'Display title';
$string['collapsible'] = 'Section collapsible?';
$string['sectioncollapsible'] = 'Section can be collapsed';
$string['sectionalwaysopen'] = 'Section always expanded';
$string['sectioncollapseddefault'] = 'Collapsed by default';
$string['sectionexpandeddefault'] = 'Expanded by default';

$string['editblock'] = 'Edit block';
$string['blocktype'] = 'Block type';
$string['wysiwyg'] = 'Content editor';
$string['button'] = 'Button';
$string['picturebutton'] = 'Picture button';

$string['buttontitle'] = 'Button caption';
$string['buttonlinktype'] = 'What is the button linking to?';
$string['buttonfile'] = 'File';
$string['buttonurl'] = 'URL';
$string['uploadfile'] = 'Upload file';
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


$string['sitesettings'] = 'Site settings';
$string['permissions'] = 'Permissions';
$string['editpermissions'] = 'Who can edit this {$a}?';
$string['viewpermissions'] = 'View Permissions';
$string['editorstype'] = 'People that can edit';
$string['sitepermissionsblurb'] = '<p>By default this website (including all pages, sections, and blocks) is editable by the site owner. Additional editors may be added by selecting users, groups, or roles below.</p>';
$string['pagepermissionsblurb'] = '<p>By default this page is editable by the site owner. Additional editors may be added by selecting users, groups, or roles below.</p>';
$string['distmultisharing'] = '<p>This feature only applies to single site ditributions.</p>';
$string['distpagesharing'] = '<p>This feature only applies to single site ditributions. The "Page for each student" distribution will automatically create a page for each student and set up the permissions allowing them to edit their own page.</p>';
$string['roles'] = 'Roles';
$string['users'] = 'Users';
$string['removeall'] = 'Remove all';
$string['nochange'] = 'No change';

$string['helplink'] = 'https://kb.cgs.act.edu.au/guides/website-builder-tool-in-cgs-connect/';

$string['targetself'] = 'Same window';
$string['targetblank'] = 'New tab';
$string['linktarget'] = 'Open in';