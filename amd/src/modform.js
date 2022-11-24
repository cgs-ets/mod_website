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
 * Provides the mod_website/modform module
 *
 * @package   mod_website
 * @category  output
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_website/modform
 */
 define(['core/log', 'core/ajax', 'core/notification'], 
 function(Log, Ajax, Notification) {    
  'use strict';

  /**
    * Initializes the modform component.
    */
  function init() {
      Log.debug('mod_website/modform: initializing');

    
      var modform = new ModForm();
      modform.main();
  }

  /**
    * The constructor
    *
    * @constructor
    */
  function ModForm() {
  }

  /**
    * Main
    *
    */
   ModForm.prototype.main = function () {
    const self = this;

    let templateurl = document.querySelector('input[name="useexistingurl"]')
    templateurl.addEventListener('input', self.TemplateUrlChanged);
    templateurl.addEventListener('propertychange', self.TemplateUrlChanged); // for IE8
  };

  //http://moodle4.local/mod/website/site.php?site=2
  ModForm.prototype.TemplateUrlChanged = function (e) {
    // Well, it changed.
    let previewwrap = document.querySelector('.site-preview-wrap')
    previewwrap.classList.remove('show')

    let templateurl = document.querySelector('input[name="useexistingurl"]').value
    const regex = /\/mod\/website\/site\.php\?site\=\d/
    const matches = regex.exec(templateurl)
    if (matches) {
      let preview = document.querySelector('.site-preview')
      preview.src = templateurl + '&mode=preview'
      previewwrap.classList.add('show')
    }
  }


  return {
      init: init
  };
});