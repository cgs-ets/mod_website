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
 * Javascript to handle changing users via the user selector in the header.
 * Based on Assign module
 * @module     mod_website/grading_navigation
 * @package    mod_website
 * @copyright  2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.1
 */
define(['jquery', 'core/notification', 'core/ajax', 'core/str', 'mod_website/save_grading',
        'mod_website/grading_form_change_check'
    ],
    function ($, notification, ajax, str, WebsiteSaveGrading, Checker) {

        /**
         * GradingNavigation class.
         *
         * @class GradingNavigation
         * @param {String} selector The selector for the page region containing the user navigation.
         */
        var GradingNavigation = function (selector) {
            console.log(" GradingNavigation constructor....");
            this._regionSelector = selector;
            this._region = $(selector);
            this._users = [];
            this._gradeFeedbackChanged = false;
            this._loadAllUsers();

            this._previousformvales = Checker.saveFormState('#gradeform');

            // Attach listeners to the select and arrow buttons.
            this._region.find('[data-action="previous-user"]').on('click', this._handlePreviousUser.bind(this));
            this._region.find('[data-action="next-user"]').on('click', this._handleNextUser.bind(this));
            this._region.find('[data-action="change-user"]').on('change', this._refreshView.bind(this));
            $(document).find('[data-region="website-info"]').on('click', this._leavepanel.bind(this));
        };

        /** @type {Boolean} Boolean tracking active ajax requests. */
        GradingNavigation.prototype._isLoading = false;

        /** @type {String} Selector for the page region containing the user navigation. */
        GradingNavigation.prototype._regionSelector = null;

        /** @type {Array} The list of active filter keys */
        GradingNavigation.prototype._filters = null;

        /** @type {Array} The list of users */
        GradingNavigation.prototype._users = null;

        /** @type {JQuery} JQuery node for the page region containing the user navigation. */
        GradingNavigation.prototype._region = null;

        GradingNavigation.prototype._gradeFeedbackChanged = null;

        /**
         * Load the list of all users for this assignment.
         * and their file info.
         * @private
         * @method _loadAllUsers
         * @return {Boolean} True if the user list was fetched.
         */
        GradingNavigation.prototype._loadAllUsers = function () {
            var select = this._region.find('[data-action=change-user]');
            var websiteid = select.attr('data-websiteid');
            var groupid = select.attr('data-groupid');

            ajax.call([{
                methodname: 'mod_website_get_participants',
                args: {
                    websiteid: websiteid,
                    groupid: groupid
                },
                done: this._setUsers.bind(this),
                fail: notification.exception
            }]);

            return true;
        };

        /**
         *      *
         * @private
         * @method _usersLoaded
         * @param {Array} users
         */
        GradingNavigation.prototype._setUsers = function (users) {
            this._users = JSON.parse(users.users);
        };

        /**
         * Change to the previous user in the grading list.
         *
         * @private
         * @method _handlePreviousUser
         * @param {Event} e
         */
        GradingNavigation.prototype._handlePreviousUser = function (e) {
            e.preventDefault();

            if (Checker.checkFormForChanges('#gradeform')) {
                this._handleChangeUserHelper(e);
            } else {
                var userid = $("select.custom-select option").filter(":selected").val();
                this._handlePreviousUser_helper(userid);
                userid = $("select.custom-select option").filter(":selected").val();
                userid = parseInt(userid, 10);
                this._refreshView(e, userid, true);

            }

        };

        GradingNavigation.prototype._handlePreviousUser_helper = function (currentSelectionid) {
            $('.custom-select option:selected').prev().attr('selected', 'selected');
            $(`select.custom-select option[value='${currentSelectionid}']`).removeAttr('selected');
        }

        /**
         * Change to the next user in the grading list.
         *
         * @param {Event} e
         * @param {Boolean} saved Has the form already been saved? Skips checking for changes if true.
         */
        GradingNavigation.prototype._handleNextUser = function (e) {
            e.preventDefault();
            var currentSelectionid = $('.custom-select option:selected').val();
            var lastSelection = $('select.custom-select option:last-child').val();

            if (Checker.checkFormForChanges('#gradeform')) {
                if (currentSelectionid == lastSelection) {
                    this._handleChangeUserHelper(0); // go back to the beginning.
                } else {
                    this._handleChangeUserHelper(e);
                }
            } else {

                if (currentSelectionid == lastSelection) {
                    userid = 0;
                } else {
                    this._handleNextUser_helper(currentSelectionid);
                    var userid = $("select.custom-select option").filter(":selected").val();
                }
                this._refreshView(e, userid, true);
            }
        };

        GradingNavigation.prototype._handleNextUser_helper = function (currentSelectionid) {
            $('select.custom-select option:selected').next().attr('selected', 'selected');
            $(`select.custom-select option[value='${currentSelectionid}']`).removeAttr('selected');
        };

        GradingNavigation.prototype.update_url = function (userid) {
            var url = new URL(window.location);
            url.searchParams.get('userid');
            url.searchParams.set('userid', userid);
            // We do this so a browser refresh will return to the same user.
            //window.history.replaceState({}, "", url);
            window.location = url;
        };

        /**
         * Respond to a user-changed event by updating the view.
         *
         * @private
         * @method _refreshView
         * @param {Event} event
         * @param {String} userid
         */
        GradingNavigation.prototype._refreshView = function (event, userid, fromhandleUser) {

            userid = fromhandleUser ? userid : event.target.value;

            if (userid == 0) {
                $('select.custom-select').val(userid);
            }

            userid = parseInt(userid, 10);

            if (Checker.checkFormForChanges('#gradeform')) {
                this._handleChangeUserHelper(userid);
            } else {
                if (!fromhandleUser) {
                    $(".custom-select option").each(function () {
                        $(this).removeAttr('selected');
                    });
                    $(`select.custom-select option[value='${userid}']`).attr('selected', 'selected')
                }
                $(document).trigger('user-changed', userid); // Refresh name
                GradingNavigation.prototype.get_user_by_id(userid);
                GradingNavigation.prototype.update_url(userid);
            }
        };

        GradingNavigation.prototype.get_user_by_id = function (userid) {

            var websiteid = String($('[data-region="user-info"]').data('websiteid'))

            ajax.call([{
                methodname: 'mod_website_get_next_participant_details',
                args: {
                    userid: userid,
                    websiteid: websiteid
                },
                done: function (response) {

                    var region = $('[data-region="grade"]');

                    region.fadeOut(300, function () {
                        region.replaceWith(response.html);
                        region.show();
                        $("#gradeform").on('submit', WebsiteSaveGrading.init()); //Re-attach the events                       
                        Checker.saveFormState('#gradeform'); //get the current data from the form
                    });
                },
                fail: function (reason) {
                    console.log('mod_website_get_participant_by_id. Unable to get elements');
                    console.log(reason)

                }
            }]);
        };



        GradingNavigation.prototype._leavepanel = function (e) {
            var anchor = e.target.closest("a");
            var url = anchor.getAttribute('href');

            if (Checker.checkFormForChanges('#gradeform')) {
                e.preventDefault();
                // Form has changes, so we need to confirm before switching users.
                str.get_strings([{
                        key: 'unsavedchanges',
                        component: 'mod_website'
                    },
                    {
                        key: 'unsavedchangesquestion',
                        component: 'mod_website'
                    },
                    {
                        key: 'saveandcontinue',
                        component: 'mod_website'
                    },
                    {
                        key: 'cancel',
                        component: 'core'
                    },
                ]).done(function (strs) {
                    notification.confirm(strs[0], strs[1], strs[2], strs[3], function () {
                        $("#gradeform").trigger('submit', [{
                            'direction': url
                        }]);
                    }, function () {
                        $("#gradeform").trigger('reset');

                    });
                });
            }
        };

        GradingNavigation.prototype._handleChangeUserHelper = function (nav) {

            // Form has changes, so we need to confirm before switching users.
            str.get_strings([{
                    key: 'unsavedchanges',
                    component: 'mod_website'
                },
                {
                    key: 'unsavedchangesquestion',
                    component: 'mod_website'
                },
                {
                    key: 'saveandcontinue',
                    component: 'mod_website'
                },
                {
                    key: 'cancel',
                    component: 'core'
                },
            ]).done(function (strs) {
                notification.confirm(strs[0], strs[1], strs[2], strs[3], function () {
                    if (nav.target != undefined) {
                        $("#gradeform").trigger('submit', [{
                            'direction': nav.target.className
                        }]);
                    } else {
                        $("#gradeform").trigger('submit', [{
                            'userid': nav
                        }]);
                    }
                }, function () {
                    $("#gradeform").trigger('reset');
                });
            });
        };

        return GradingNavigation;
    });