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
 * Provides the mod_website/site module
 *
 * @package   mod_website
 * @category  output
 * @copyright 2022 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_website/site
 */
 define(['core/log', 'core/ajax'], 
 function(Log, Ajax) {    
  'use strict';

  /**
    * Initializes the site component.
    */
  function init() {
      Log.debug('mod_website/site: initializing');

      var rootel = document.querySelector(".site-root");
      if (rootel === null) {
          Log.error('mod_website/site: .site-root not found!');
          return;
      }

      var site = new Site(rootel);
      site.main();
  }

  /**
    * The constructor
    *
    * @constructor
    * @param rootel
    */
   function Site(rootel) {
    var self = this;
    self.rootel = rootel;
    self.editingsetup = false;
  }

  /**
    * Run the Audience Selector.
    *
    */
  Site.prototype.main = function () {
    var self = this

    // Set up editing.
    self.setupEditing();

    let editswitch = document.querySelector('.site-editor-switch')
    editswitch && editswitch.addEventListener('change', (e) => {
      let mode = 0
      if (e.currentTarget.checked) {
        self.rootel.dataset.mode = 'edit'
        mode = 1
        self.setupEditing();
      } else {
        self.rootel.dataset.mode = 'view'
        // Disable block sorting.
        const sections = document.querySelectorAll('.site-section')
        sections.forEach(section => {
          let sortable = Sortable.get(section)
          if (sortable !== undefined) {
            sortable.option("disabled", true)
          }
        })
      }
      Ajax.call([{
        methodname: 'mod_website_apicontrol',
        args: { 
            action: 'update_mode',
            data: JSON.stringify({
                mode: mode,
            }),
        }
      }])
    })

    if (self.rootel.dataset.canedit == '1') {
      document.querySelectorAll('.editzone').forEach(item => {
        item.addEventListener('click', e => {
          e.stopPropagation();
          if ( self.rootel.dataset.mode !== 'edit') { return; }
          // redirect.
          let url = e.currentTarget.dataset.url
          if (url) {
            window.location.href = url;
          }
        })
        item.addEventListener('mouseenter', e => {
          // When going from a parent into a child, mouseleave is not triggered on the parent, so here we need to remove all existing hovers when entering a new editzone.
          document.querySelectorAll('.editzone.hover').forEach(z => {
            z.classList.remove("hover")
          })
          e.target.classList.add("hover")
        })
        item.addEventListener('mouseleave', e => {
          e.target.classList.remove("hover")
          // If going from a child editzone into a parent editzone, add hover to the parent as mouseenter would not be triggered.
          if (e.toElement && ( e.toElement.classList.contains('editzone') )) {
            e.toElement.classList.add("hover")
          }
          else if (e.toElement.parentNode.classList.contains('editzone')) {
            e.toElement.parentNode.classList.add("hover")
          }
        })
      })
    }

    // Check menu width.
    self.checkMenuWidth()
    window.addEventListener('resize', function(event) {
      self.checkMenuWidth()
    }, true)

    // Menu Toggle.
    let menutoggle = document.querySelector('.menutoggle');
    menutoggle && menutoggle.addEventListener('click', e => {
      const menu = document.querySelector('.menu')
      if (menu.classList.contains('expanded')) {
        menu.classList.remove('expanded');
      } else {
        menu.classList.add('expanded');
      }
    })

    document.querySelectorAll('.menuitem.haschildren > a').forEach(a => {
      a.addEventListener('click', event => {
        // If in mobile view...
        const menuwrap = document.querySelector('.menuwrap')
        if (menuwrap.classList.contains('mobile')) {
          event.preventDefault();
          if (a.parentNode.classList.contains('expanded')) {
            a.parentNode.classList.remove('expanded');
          } else {
            a.parentNode.classList.add('expanded');
          }
        }
      })
    })

    // Sections Toggle.
    document.querySelectorAll('.site-section[data-collapsible="true"] .section-title').forEach(a => {
      a.addEventListener('click', e => {
        if ( e.currentTarget .parentNode.classList.contains('collapsed') ) {
          e.currentTarget .parentNode.classList.remove('collapsed')
        } else {
          e.currentTarget .parentNode.classList.add('collapsed')
        }
      })
    })

  };

  Site.prototype.checkMenuWidth = function () {
    const right = document.querySelector('.topbar-right')
    const menu = document.querySelector('.menuwrap')
    const topbar = document.querySelector('.topbar')
    const logo = document.querySelector('.logo')

    // Start check.
    menu.classList.add("checkingsize")

    // Get element in desktop view to measure width.
    menu.classList.remove("mobile")
    let rightwidth = right.offsetWidth;
    let topbarwidth = topbar.offsetWidth;
    let logowidth = logo.offsetWidth;

    // If menu is too big, add mobile view.
    if (rightwidth > topbarwidth - logowidth - 50) { // 50 px buffer.
      menu.classList.add("mobile")
    }
    // Done checking.
    menu.classList.remove("checkingsize")
  }


  Site.prototype.setupEditing = function () {
    var self = this

    // Make sure edit mode is on.
    if ( self.rootel.dataset.mode !== 'edit') { return; }

    // Set up drag reordering of tasks.
    if (typeof Sortable === 'undefined') {
      return false;
    }

    const sections = document.querySelectorAll('.section-blocks');
    if (sections.length === 0) {
      return false;
    }

    // Check if already set up.
    if ( self.editingsetup ) { 
      // Enable block sorting
      sections.forEach(section => {
        let sortable = Sortable.get(section)
        if (sortable !== undefined) {
          sortable.option("disabled", false)
          return
        }
      })
    }

    // Only setup once.
    self.editingsetup = true;
    
    // Setup block sorting.
    sections.forEach(section => {
      new Sortable(section, {
        group: 'sharedsections',
        draggable: ".site-block",
        animation: 150,
        ghostClass: 'reordering',
        onStart: self.BlockSortStart,
        onEnd: self.BlockSortEnd,
      })
    })

    // Setup section sorting.
    /*const sectionswrap = document.querySelector('.site-sections');
    new Sortable(sectionswrap, {
      draggable: ".site-section",
      animation: 150,
      ghostClass: 'reordering',
      onStart: self.SectionSortStart,
      onEnd: self.SectionSortEnd,
    })*/

  }

  Site.prototype.BlockSortStart = function (e) {
    let rootel = document.querySelector(".site-root");
    if ( rootel.dataset.mode !== 'edit') { return; }
    rootel.classList.add("sorting")

    document.querySelectorAll('.site-section').forEach(section => {
      section.style["width"] = section.offsetWidth + "px";
      section.style["height"] = section.offsetHeight + "px";
      section.style["overflow"] = 'hidden';
    });
  }

  Site.prototype.BlockSortEnd = function (e) {
    let rootel = document.querySelector(".site-root");

    if ( rootel.dataset.mode !== 'edit') { return; }

    rootel.classList.remove("sorting")

    document.querySelectorAll('.site-section').forEach(section => {
      section.style["width"] = '';
      section.style["height"] = '';
      section.style["overflow"] = '';
    })

    // Save the new order.
    // Start with "from" section.
    let order = [];
    for (const block of e.from.children) {
      if (block.dataset.blockid) {
        order.push(block.dataset.blockid);
      }
    }
    Ajax.call([{
      methodname: 'mod_website_apicontrol',
      args: { 
          action: 'reorder_blocks',
          data: JSON.stringify({
              sectionid: e.from.dataset.sectionid,
              blocks: JSON.stringify(order)
          }),
      }
    }])
  
    // Then do the "to" section.
    order = [];
    if (e.from.className != e.to.className) {
      for (const block of e.to.children) {
        if (block.dataset.blockid) {
          order.push(block.dataset.blockid);
        }
      }
      Ajax.call([{
        methodname: 'mod_website_apicontrol',
        args: { 
            action: 'reorder_blocks',
            data: JSON.stringify({
                sectionid: e.to.dataset.sectionid,
                blocks: JSON.stringify(order)
            }),
        }
      }])
    }
  }

  Site.prototype.SectionSortStart = function (e) {
    let rootel = document.querySelector(".site-root");

    if ( rootel.dataset.mode !== 'edit') { return; }

    rootel.classList.add("sorting-sections")
  }

  Site.prototype.SectionSortEnd = function (e) {
    let rootel = document.querySelector(".site-root");

    if ( rootel.dataset.mode !== 'edit') { return; }

    rootel.classList.remove("sorting-sections")
  }

  return {
      init: init
  };
});