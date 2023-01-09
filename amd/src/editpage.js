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
 * Provides the mod_website/editpage module
 *
 * @package   mod_website
 * @category  output
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_website/editpage
 */
 define(['core/log', 'core/ajax', 'core/notification'], 
 function(Log, Ajax, Notification) {    
  'use strict';

  /**
    * Initializes the editpage component.
    */
  function init() {
      Log.debug('mod_website/editpage: initializing');

    
      var editpage = new EditPage();
      editpage.main();
  }

  /**
    * The constructor
    *
    * @constructor
    */
  function EditPage() {
  }

  /**
    * Main
    *
    */
  EditPage.prototype.main = function () {
    const self = this;

    let deletebutton = document.querySelector('.btn-delete')
    deletebutton.addEventListener('click', e => {
      e.preventDefault();
      Ajax.call([{
        methodname: 'mod_website_apicontrol',
        args: { 
            action: 'delete_page',
            data: deletebutton.dataset.pageid,
        },
        done: function (e) {
          window.open(deletebutton.dataset.returnurl, '_parent');
        },
        fail: Notification.exception
      }]);
    })
  };


  return {
      init: init
  };
});