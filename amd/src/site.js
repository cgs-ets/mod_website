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
 define(['core/log', 'core/ajax', 'core/config'],
 function(Log, Ajax, Config) {
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
    self.editingawake = false;
  }

  /**
    * Run the Audience Selector.
    *
    */
  Site.prototype.main = function () {
    var self = this

    // Set up editing.
    self.wakeupEditing();

    // Edit mode switch.
    let editswitch = document.querySelector('.site-editor-switch')
    editswitch && editswitch.addEventListener('change', (e) => {
      let mode = 0
      if (e.currentTarget.checked) {
        self.rootel.dataset.mode = 'edit'
        mode = 1
        self.wakeupEditing();
      } else {
        self.rootel.dataset.mode = 'view'
        self.disableBlockSorting();
        self.disableSectionSorting();
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

    // Full screen toggler.
    let fullscreentoggle = document.querySelector('.btn-fullscreen')
    fullscreentoggle && fullscreentoggle.addEventListener('click', (e) => {
      if (document.body.classList.contains('fullscreen')) {
        document.body.classList.remove('fullscreen')
      } else {
        document.body.classList.add('fullscreen')
      }
    })

    // Block popup content.
    document.querySelectorAll('.picturebutton-type-content').forEach(btn => {
      btn.addEventListener('click', e => {
        if (self.rootel.dataset.mode === 'edit') { return; }

        e.preventDefault();
        e.stopPropagation();

        let parent = e.currentTarget.parentNode;
        //let contentbody = parent.querySelector('.picturebutton-content');
        //let modalbody = document.querySelector('#modal-popupcontent .modal__body');
        //modalbody.innerHTML = contentbody.innerHTML;

        let modalbody = document.querySelector('#modal-popupcontent .modal__body')

        // Dont copy, move content so multimedia players still work.
        if (parent.querySelector('.picturebutton-content').dataset.loadwithpage == '1') {
          modalbody.appendChild(parent.querySelector('.picturebutton-content'))
        } else
        {
          // If load via iframe, then inject an iframe with a src to the block content. This is needed to load Youtube videos properly in popup. Also, using this can improve performance of site load.
          modalbody.textContent = '';
          var makeIframe = document.createElement("iframe");
          makeIframe.setAttribute("src", parent.querySelector('.picturebutton-content').dataset.contenturl);
          modalbody.appendChild(makeIframe);
          modalbody.classList.add("no_padding");
        }

        let modalstate = document.getElementById('modal-state-popupcontent');
        modalstate.checked = true;
      })
    })

    document.getElementById('modal-state-popupcontent').addEventListener('change', function (e) {
      if (!this.checked) {
        let modalbody = document.querySelector('#modal-popupcontent .modal__body')
        // Add padding back to modal body in case of preloaded content (not iframe)
        if (modalbody.classList.contains('no_padding')) {
          modalbody.classList.remove("no_padding");
          // Remove iframe loaded based content.
          modalbody.textContent = '';
        } else {
          // Move content back.
          let modalblockcontent = document.querySelector('#modal-popupcontent .modal__body .picturebutton-content');
          document.querySelector('.site-block[data-blockid="' + modalblockcontent.dataset.id + '"] .picturebutton-wrap').appendChild(document.querySelector('#modal-popupcontent .modal__body .picturebutton-content'));
          // pause all media.
          document.querySelectorAll('audio').forEach(aud => aud.pause());
          document.querySelectorAll('video').forEach(vid => vid.pause());
        }
      }
    })

    // User is editor in some capacity (site or page).
    if (self.rootel.dataset.caneditpage == '1') {

      // Embedded forms.
      document.querySelectorAll('[data-formurl]').forEach(item => {
        item.addEventListener('click', e => {
          if (self.rootel.dataset.mode !== 'edit') { return; }

          e.preventDefault();
          e.stopPropagation();

          if (document.querySelector('.site-sections').classList.contains('sorting')) { return; }

          let formurl = e.currentTarget.dataset.formurl
          if (formurl) {
            //window.location.href = url;
            let modalbody = document.querySelector('#modal-embeddedform .modal__body');
            if (modalbody.dataset.currentform != formurl) {
              modalbody.dataset.currentform = formurl
              modalbody.innerHTML = '<iframe src="' + formurl + '"></iframe>';
            }
            let modalstate = document.getElementById('modal-state-embeddedform');
            modalstate.checked = true;
          }
        })
      })

      // Edit zone hovering.
      document.querySelectorAll('.editzone').forEach(item => {
        item.addEventListener('mouseenter', e => {
          if (self.rootel.dataset.mode !== 'edit') { return; }

          e.stopPropagation();

          // When going from a parent into a child, mouseleave is not triggered on the parent, so here we need to remove all existing hovers when entering a new editzone.
          document.querySelectorAll('.editzone.hover').forEach(z => {
            z.classList.remove("hover")
          })
          e.target.classList.add("hover")
        })

        item.addEventListener('mouseleave', e => {
          if (self.rootel.dataset.mode !== 'edit') { return; }
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

      // Dropzones
      Dropzone.autoDiscover = false;
      let rootel = document.querySelector(".site-root");
      document.querySelectorAll('.site-section').forEach(section => {
        let uploadURL = Config.wwwroot + "/mod/website/dropzone.php?site=" + rootel.dataset.siteid + "&section=" + section.dataset.sectionid + "&upload=1&sesskey=" + Config.sesskey
        let dz  = new Dropzone(section, {
          url: uploadURL,
          clickable: false,
        });
        dz.on("queuecomplete", function (file) {
          setTimeout(location.reload(), 2000);
        });
        dz.on("error", function(file, message) {
          console.log(message);
        });
        dz.on("addedfiles", function() {
          let previews = document.getElementsByClassName('dz-preview');
          let wrapper = document.createElement('div');
          wrapper.className = "dz-preview-wrapper";
          self.wrapAll(wrapper, previews);
        });
      });

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

    // Expand tier 2
    document.querySelectorAll('.menu > .menuitem.haschildren > a').forEach(a => {
      a.addEventListener('click', event => {
        // If in mobile view.
        const topbar = document.querySelector('.topbar')
        if (topbar.classList.contains('mobile')) {
          event.preventDefault();
          if (a.parentNode.classList.contains('expanded')) {
            a.parentNode.classList.remove('expanded');
          } else {
            a.parentNode.classList.add('expanded');
          }
        }
      })
    })

    // Expand tier 3
    document.querySelectorAll('.submenu > .menuitem.haschildren > a').forEach(a => {
      a.addEventListener('click', event => {
        event.preventDefault();
        if (a.parentNode.classList.contains('expanded')) {
          a.parentNode.classList.remove('expanded');
        } else {
          a.parentNode.classList.add('expanded');
        }
      })
    })

    let auxsection = document.querySelectorAll('.site-section[data-collapsible="true"] .section-title').length > 0
                  ? document.querySelectorAll('.site-section[data-collapsible="true"] .section-title')
                  : document.querySelectorAll('.site-section[data-collapsible="true"] .section-title-cgs-branding');
    // Collapse/expand sections toggle.
    auxsection.forEach(a => {
      a.addEventListener('click', e => {
        if ( e.currentTarget .parentNode.classList.contains('collapsed') ) {
          e.currentTarget .parentNode.classList.remove('collapsed')
        } else {
          e.currentTarget .parentNode.classList.add('collapsed')
        }
      })
    })

    // Toggle section sorting.
    let sectionsortingtoggle = document.querySelector('.btn-sort-sections');
    sectionsortingtoggle && sectionsortingtoggle.addEventListener('click', e => {
      e.preventDefault();
      const sectionswrap = document.querySelector('.site-sections');
      if (sectionswrap.classList.contains('sorting')) {
        self.disableSectionSorting();
        sectionswrap.classList.remove('sorting');
      } else {
        self.enableSectionSorting();
        sectionswrap.classList.add('sorting');
      }
    })

    self.rootel.classList.add('js-loaded')

  };

  Site.prototype.checkMenuWidth = function () {
    const right = document.querySelector('.topbar-right')
    const menu = document.querySelector('.menuwrap')
    const topbar = document.querySelector('.topbar')
    const logo = document.querySelector('.logo')

    // Start check.
    menu.classList.add("checkingsize")

    // Get element in desktop view to measure width.
    topbar.classList.remove("mobile")
    let rightwidth = right.offsetWidth;
    let topbarwidth = topbar.offsetWidth;
    let logowidth = logo.offsetWidth;
    console.log("Right width: " + rightwidth);
    console.log("Topbar width: " + topbarwidth);
    console.log("Logo width: " + logowidth);

    // If menu is too big, add mobile view.
    if (rightwidth > topbarwidth - logowidth - 50) { // 50 px buffer.
      topbar.classList.add("mobile")
    }
    // Done checking.
    menu.classList.remove("checkingsize")
  }


  Site.prototype.wakeupEditing = function () {
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
    if ( self.editingawake ) {
      self.enableBlockSorting();
      self.enableSectionSorting();
    }

    // Only setup once.
    self.editingawake = true;

    // Setup sorting.
    self.initBlockSorting()
    self.initSectionSorting()
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
    Log.debug(e.from);
    Log.debug(e.to);

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
              sectionid: e.from.parentNode.dataset.sectionid,
              blocks: JSON.stringify(order)
          }),
      }
    }])

    // Then do the "to" section.
    order = [];
    if (e.from.parentNode.dataset.sectionid != e.to.parentNode.dataset.sectionid) {
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
                sectionid: e.to.parentNode.dataset.sectionid,
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

    // Save the new order.
    let order = [];
    const sections = document.querySelectorAll('.site-section')
    sections.forEach(section => {
      if (section.dataset.sectionid) {
        order.push(section.dataset.sectionid)
      }
    })
    Ajax.call([{
      methodname: 'mod_website_apicontrol',
      args: {
          action: 'reorder_sections',
          data: JSON.stringify({
              pageid: rootel.dataset.currentpage,
              sections: JSON.stringify(order)
          }),
      }
    }])
  }

  Site.prototype.initBlockSorting = function () {
    let self = this;
    const sections = document.querySelectorAll('.section-blocks')
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
  }

  Site.prototype.disableBlockSorting = function () {
    const sections = document.querySelectorAll('.section-blocks')
    sections.forEach(section => {
      let sortable = Sortable.get(section)
      if (sortable !== undefined) {
        sortable.option("disabled", true)
      }
    })
  }

  Site.prototype.enableBlockSorting = function () {
    const sections = document.querySelectorAll('.section-blocks');
    sections.forEach(section => {
      let sortable = Sortable.get(section)
      if (sortable !== undefined) {
        sortable.option("disabled", false)
        return
      }
    })
  }

  Site.prototype.initSectionSorting = function () {
    let self = this;
    const sectionswrap = document.querySelector('.site-sections');
    new Sortable(sectionswrap, {
      draggable: ".site-section",
      animation: 150,
      ghostClass: 'reordering',
      onStart: self.SectionSortStart,
      onEnd: self.SectionSortEnd,
    })
  }

  Site.prototype.disableSectionSorting = function () {
    const sectionswrap = document.querySelector('.site-sections');
    let sectionssortable = Sortable.get(sectionswrap)
    if (sectionssortable !== undefined) {
      sectionssortable.option("disabled", true)
    }
  }

  Site.prototype.enableSectionSorting = function () {
    const sectionswrap = document.querySelector('.site-sections');
    let sectionsortable = Sortable.get(sectionswrap)
    if (sectionsortable !== undefined) {
      sectionsortable.option("disabled", false)
      return
    }
  }

  /**
   * Wrap an HTMLElement around another set of elements
   * Modified global function based on Kevin Jurkowski's implementation
   * here: http://stackoverflow.com/questions/3337587/wrapping-a-dom-element-using-pure-javascript/13169465#13169465
   */
   Site.prototype.wrapAll = function(wrapper, elms) {
    var el = elms.length ? elms[0] : elms,
        parent  = el.parentNode;

    wrapper.appendChild(el);

    while (elms.length) {
      wrapper.appendChild(elms[0]);
    }

    parent.appendChild(wrapper);
  }

  return {
      init: init
  };
});