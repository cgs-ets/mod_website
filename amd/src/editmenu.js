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
 * Provides the mod_website/editmenu module
 *
 * @package   mod_website
 * @category  output
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_website/editmenu
 */
 define(['core/log'], 
 function(Log) {    
  'use strict';

  /**
    * Initializes the editmenu component.
    */
  function init() {
      Log.debug('mod_website/editmenu: initializing');

    
      var editmenu = new Editmenu();
      editmenu.main();
  }

  /**
    * The constructor
    *
    * @constructor
    */
  function Editmenu() {
  }

  /**
    * Main
    *
    */
   Editmenu.prototype.main = function () {
    var self = this;
    // Set up menu item sorting on edit-menu.php
    // Loop through each nested sortable element
    const nestedSortables = document.querySelectorAll('.nested-sortable.list-move')
    for (var i = 0; i < nestedSortables.length; i++) {
      new Sortable(nestedSortables[i], {
        group: {
          name: 'nestedmenu',
          pull: true, // Move (not clone) out of this list.
        },
        animation: 150,
        fallbackOnBody: true,
        swapThreshold: 0.65,
        ghostClass: 'reordering',
        onStart: self.SortStart,
        onEnd: self.SortEnd,
      });
    }

    const nestedSortablesClonable = document.querySelectorAll('.nested-sortable.list-clone')
    for (var i = 0; i < nestedSortablesClonable.length; i++) {
      new Sortable(nestedSortablesClonable[i], {
        group: {
          name: 'nestedmenu',
          pull: 'clone', // Clone from this list.
        },
        animation: 150,
        fallbackOnBody: true,
        swapThreshold: 0.65,
        ghostClass: 'reordering',
        onStart: self.SortStart,
        onEnd: self.SortEnd,
      });
    }

  };

  Editmenu.prototype.SortStart = function (e) {
    let menuel = document.querySelector(".active .menu-list")
    menuel.classList.add("sorting")
  }
  
  Editmenu.prototype.SortEnd = function (e) {
    let menuel = document.querySelector(".active .menu-list")
    menuel.classList.remove("sorting")

    // Taken from all to active.
    if (e.from.classList.contains('list-all') && e.to.classList.contains('list-active')) {
      let isinactive = document.querySelectorAll('.inactive .list-group-item[data-pageid="' + e.item.dataset.pageid + '"]').length
      // It is present in the inactive list. Remove it.
      if ( isinactive ) {
        let item = document.querySelector('.inactive .list-group-item[data-pageid="' + e.item.dataset.pageid + '"]')
        item.remove()
      }
    }

    // Taken from active to all.
    if (e.from.classList.contains('list-active') && e.to.classList.contains('list-all')) {
      let isactive = document.querySelectorAll('.active .list-group-item[data-pageid="' + e.item.dataset.pageid + '"]').length
      // If it is still present elsewhere in the active list, just remove it.
      if ( isactive ) {
        e.item.remove()
      }
      let isinactive = document.querySelectorAll('.inactive .list-group-item[data-pageid="' + e.item.dataset.pageid + '"]').length
      // It is not in the active list, and it is not in the inactive list, and it is not already being moved to inactive. Move it to inactive.
      if ( !isactive && !isinactive ){
        let inactive = document.querySelector(".inactive .menu-list")
        inactive.appendChild(e.item)
      }
    }

    // Taken from active to inactive.
    if (e.from.classList.contains('list-active') && e.to.classList.contains('list-inactive')) {
      let isactive = document.querySelectorAll('.active .list-group-item[data-pageid="' + e.item.dataset.pageid + '"]').length
      if ( isactive ) {
        e.item.remove()
      }
    }

    // Taken from inactive to all.
    if (e.from.classList.contains('list-inactive') && e.to.classList.contains('list-all')) {
      // Put it back.
      let inactive = document.querySelector(".inactive .menu-list")
      inactive.appendChild(e.item)
    }

    // Taken from all to inactive.
    if (e.from.classList.contains('list-all') && e.to.classList.contains('list-inactive')) {
      e.item.remove()
    }

    // Create the new json.
    let menu = []
    for (const parent of menuel.children) {
      let children = parent.querySelectorAll(".list-group-item")
      let submenu = []
      for (const child of children) {
        submenu.push({
          'id' : child.dataset.pageid
        })
      }
      menu.push({
        'id' : parent.dataset.pageid,
        'children' : submenu
      })
    }
    
    var menuJSON = document.querySelector('input[name="menujson"]')
    menuJSON.value = JSON.stringify(menu)
  }

  return {
      init: init
  };
});