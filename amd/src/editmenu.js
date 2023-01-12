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
 define(['core/log', 'core/ajax'], 
 function(Log, Ajax) {    
  'use strict';

  /**
    * Initializes the editmenu component.
    */
  function init() {
      Log.debug('mod_website/editmenu: initializing')

    
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

    // Page visibility.
    document.querySelectorAll('.visibility-visible').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        Ajax.call([{
          methodname: 'mod_website_apicontrol',
          args: { 
              action: 'page_visibility',
              data: JSON.stringify({
                  pageid: btn.dataset.pageid,
                  hidden: 1,
              }),
          }
        }])
        document.querySelectorAll('.list-group-item[data-pageid="' + btn.dataset.pageid + '"]').forEach(item => {
          item.dataset.hidden = 1
        })
      })
    })
    document.querySelectorAll('.visibility-hidden').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        Ajax.call([{
          methodname: 'mod_website_apicontrol',
          args: { 
              action: 'page_visibility',
              data: JSON.stringify({
                  pageid: btn.dataset.pageid,
                  hidden: 0,
              }),
          }
        }])
        document.querySelectorAll('.list-group-item[data-pageid="' + btn.dataset.pageid + '"]').forEach(item => {
          item.dataset.hidden = 0
        })
      })
    })

    // Page promote to homepage.
    document.querySelectorAll('.promote-to-homepage').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        Ajax.call([{
          methodname: 'mod_website_apicontrol',
          args: { 
            action: 'promotetohome',
            data: btn.dataset.pageid
          },
          done: function (e) {
            document.querySelectorAll('.list-group-item[data-ishomepage="1"]').forEach(item => {
              item.dataset.ishomepage = 0
            })
            document.querySelectorAll('.list-group-item[data-pageid="' + btn.dataset.pageid + '"]').forEach(item => {
              item.dataset.ishomepage = 1
            })
          },
        }])
      })
    })


    // Target
    document.querySelectorAll('.target').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault()
        e.stopPropagation()
        let elem = e.currentTarget
        let target = elem.dataset.target
        for ( ; elem && elem !== document; elem = elem.parentNode ) {
          if ( elem.classList.contains('list-group-item') ) {
            elem.dataset.target = target
            self.regenerateAttributes(elem)
            self.regenerateJson()
            return
          }
        }
      })
    })

  };


  Editmenu.prototype.setTarget = function (elem, target) {
    var self = this
    // find the parent item elem.
    
  }

  Editmenu.prototype.SortStart = function (e) {
    let menuel = document.querySelector(".active .menu-list")
    menuel.classList.add("sorting")
  }
  
  Editmenu.prototype.SortEnd = function (e) {
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

    var editmenu = new Editmenu();
    editmenu.regenerateJson();
  }

  Editmenu.prototype.regenerateAttributes = function (item) {
    let attributes = {
      target : '',
    }
    if (item.dataset.attributes) {
      attributes = JSON.parse(item.dataset.attributes)
    }
    attributes.target = item.dataset.target
    item.dataset.attributes = JSON.stringify(attributes)
  }

  Editmenu.prototype.regenerateJson = function () {
    let menuel = document.querySelector(".active .menu-list")
    menuel.classList.remove("sorting")

    // Create the new json.
    let menu = []
    for (const parent of menuel.children) {
      let tier2menu = []
      let tier2children = parent.querySelectorAll(".list-group-item-tier2")
      for (const tier2child of tier2children) {
        let tier3menu = []
        let tier3children = tier2child.querySelectorAll(".list-group-item-tier3")
        for (const tier3child of tier3children) {
          console.log("Pushing " + tier3child.dataset.pageid + " into tier 3")
          tier3menu.push({
            'id' : tier3child.dataset.pageid,
            'attributes' : tier3child.dataset.attributes,
          })
        }
        console.log("Pushing " + tier2child.dataset.pageid + " into tier 2")
        tier2menu.push({
          'id' : tier2child.dataset.pageid,
          'attributes' : tier2child.dataset.attributes,
          'children' : tier3menu,
        })
      }
      console.log("Pushing " + parent.dataset.pageid + " into top")
      menu.push({
        'id' : parent.dataset.pageid,
        'attributes' : parent.dataset.attributes,
        'children' : tier2menu,
      })
    }

    var menuJSON = document.querySelector('input[name="menujson"]')
    menuJSON.value = JSON.stringify(menu)
  }

  return {
      init: init
  };
});