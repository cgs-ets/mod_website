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
 * @copyright 2025 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_website/site
 */

define( ['core/log', 'jquery'], function (Log, $){

    'use strict';

    function init () {
        Log.debug('mod_website/logcontrol: initializing');

        var lc = new LogControl();
        lc.main();
    }

    function LogControl() {

    }

    LogControl.prototype.main = function () {
        var self = this;
        var opts = {pagerSelector:'#myPager',showPrevNext:true,hidePageNumbers:false,perPage:10};
        self.pagination(opts)
    }


    LogControl.prototype.pagination = function(opts) {
        var $this = $('#mod-website-logs'),
            defaults = {
                perPage: 10,
                showPrevNext: true,
                hidePageNumbers: false
            },
            settings = $.extend(defaults, opts);

        var listElement = $this;
        var perPage = settings.perPage;
        var children = listElement.children();
        var pager = $('.pager');

        if (typeof settings.childSelector != "undefined") {
            children = listElement.find(settings.childSelector);
        }

        if (typeof settings.pagerSelector != "undefined") {
            pager = $(settings.pagerSelector);
        }

        var numItems = children.length;
        var numPages = Math.ceil(numItems / perPage);

        pager.data("curr", 0);
        pager.empty(); // Clear existing pagination

        // Add previous button if needed
        if (settings.showPrevNext && numPages > 1) {
            $('<li class="page-item"><a href="#" class="prev_link page-link" aria-label="Previous"><span aria-hidden="true">«</span></a></li>').appendTo(pager);
        }

        // Add page numbers if needed
        if (!settings.hidePageNumbers) {
            for (var i = 0; i < numPages; i++) {
                $('<li class="page-item"><a href="#" class="page-link">' + (i + 1) + '</a></li>')
                    .appendTo(pager)
                    .data("page-index", i);
            }
        }

        // Add next button if needed
        if (settings.showPrevNext && numPages > 1) {
            $('<li class="page-item"><a href="#" class="next_link page-link" aria-label="Next"><span aria-hidden="true">»</span></a></li>').appendTo(pager);
        }

        // Initial state
        children.hide().slice(0, perPage).show();
        updatePagerState(0);

        // Click handlers
        pager.on('click', '.page-link:not(.prev_link):not(.next_link)', function() {
            var pageIndex = $(this).parent().data("page-index");
            goTo(pageIndex);
            return false;
        });

        pager.on('click', '.prev_link', function() {
            var currPage = pager.data("curr");
            if (currPage > 0) {
                goTo(currPage - 1);
            }
            return false;
        });

        pager.on('click', '.next_link', function() {
            var currPage = pager.data("curr");
            if (currPage < numPages - 1) {
                goTo(currPage + 1);
            }
            return false;
        });

        function goTo(page) {
            if (page < 0 || page >= numPages) return;

            var startAt = page * perPage;
            var endOn = startAt + perPage;

            children.hide().slice(startAt, endOn).show();
            pager.data("curr", page);
            updatePagerState(page);
        }

        function updatePagerState(currentPage) {
            // Update active state
            pager.find('.page-item').removeClass('active');
            if (!settings.hidePageNumbers) {
                pager.find('.page-item').eq(currentPage + (settings.showPrevNext ? 1 : 0)).addClass('active');
            }

            // Update prev/next visibility
            pager.find('.prev_link').toggle(currentPage > 0);
            pager.find('.next_link').toggle(currentPage < numPages - 1);
        }
    };

    return {
        init: init
    };
})