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
 * Provides the mod_website/editblock module
 *
 * @package   mod_website
 * @category  output
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_website/editblock
 */
 define(['core/log', 'core/ajax', 'core/notification'], 
 function(Log, Ajax, Notification) {    
  'use strict';

  /**
    * Initializes the editblock component.
    */
  function init() {
      Log.debug('mod_website/editblock: initializing')

    
      var editblock = new Editblock()
      editblock.main()
  }

  /**
    * The constructor
    *
    * @constructor
    */
  function Editblock() {
  }

  /**
    * Main
    *
    */
  Editblock.prototype.main = function () {
    const self = this;

    self.checkBlockType()
    let selectedlinktype = document.querySelector('.linktype input:checked')
    if (!selectedlinktype) {
      // Default is file.
      document.querySelector('.linktype input[value="file"]').checked = true
    }
    self.checkLinkType()
    //self.checkIncludePicture();

    // Block type changed.
    let blocktypes = document.querySelectorAll('.blocktype input')
    for (var i = 0; i < blocktypes.length; i++) {
      blocktypes[i].addEventListener('change', (e) => {self.checkBlockType()})
    }

    // Link type type changed.
    let linktypes = document.querySelectorAll('.linktype input')
    for (var i = 0; i < linktypes.length; i++) {
      linktypes[i].addEventListener('change', (e) => {self.checkLinkType()})
    }

    // Link type type changed.
    /*let includepicture = document.querySelectorAll('.includepicture input')
    for (var i = 0; i < includepicture.length; i++) {
      includepicture[i].addEventListener('change', (e) => {self.checkIncludePicture()})
    }*/

    let deletebutton = document.querySelector('.btn-delete')
    deletebutton.addEventListener('click', e => {
      e.preventDefault();
      Ajax.call([{
        methodname: 'mod_website_apicontrol',
        args: { 
            action: 'delete_block',
            data: deletebutton.dataset.blockid,
        },
        done: function (e) {
          window.open(deletebutton.dataset.returnurl, '_parent')
        },
        fail: Notification.exception
      }]);
    })
    

  };

  // Determine block type.
  Editblock.prototype.checkBlockType = function () {
    let blocktype = document.querySelector('.blocktype input:checked').value
    let form = document.querySelector('form[data-form="website-siteblock"]')
    form.dataset.blocktype = blocktype;
  }

  // Determine link type.
  Editblock.prototype.checkLinkType = function () {
    let linktype = document.querySelector('.linktype input:checked')
    if (linktype == null) {
      return
    }
    let form = document.querySelector('form[data-form="website-siteblock"]')
    form.dataset.linktype = linktype.value;
  }

  // Check if we're including a button picture.
  Editblock.prototype.checkIncludePicture = function () {
    let includepicture = document.querySelector('.includepicture input:checked')
    if (includepicture == null) {
      return
    }
    let form = document.querySelector('form[data-form="website-siteblock"]')
    form.dataset.includepicture = includepicture.value;
  }

  return {
      init: init
  };
});