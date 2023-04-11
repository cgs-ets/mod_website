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
 * Provides the mod_website/recyclebin module
 *
 * @package   mod_website
 * @category  output
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_website/recyclebin
 */
 define(['core/log', 'core/ajax', 'core/notification'], 
 function(Log, Ajax, Notification) {    
  'use strict';

  /**
    * Initializes the recyclebin component.
    */
  function init() {
      Log.debug('mod_website/recyclebin: initializing')
    
      var recyclebin = new RecycleBin()
      recyclebin.main()
  }

  /**
    * The constructor
    *
    * @constructor
    */
  function RecycleBin() {
  }

  /**
    * Main
    *
    */
  RecycleBin.prototype.main = function () {
    document.querySelectorAll('.btn-restore').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        const id = btn.dataset.id
        const type = btn.dataset.type
        const siteid = btn.dataset.siteid
        const newDiv = document.createElement("div");
        const newContent = document.createTextNode("Submitting...")
        newDiv.appendChild(newContent);
        btn.replaceWith(newDiv);
        Ajax.call([{
          methodname: 'mod_website_apicontrol',
          args: { 
              action: 'restore_deleted',
              data: JSON.stringify({
                id: id,
                type: type,
                siteid: siteid,
              }),
          },
          done: function (e) {
            newDiv.innerText = 'Restored...'
          },
          fail: function (e) {
            newDiv.innerText = 'Failed...'
          },
        }])
      })
    })
  }


  return {
      init: init
  };
});