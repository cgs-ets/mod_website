// Standard license block omitted.
/*
 * @package    mod_website
 * @copyright  2020 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_website/update_controls
 *
 */
define(['jquery', 'core/ajax', 'core/log', 'core/notification', 'mod_website/grading_form_change_check'], function ($, Ajax, Log, notification, Checker) {
    'use strict';
    /**
     * Initializes the update controls.
     */
    function init() {
        Log.debug('mod_website/SaveGrading: initializing SaveGrading of the mod_website');
        var control = new WebsiteSaveGrading();

        control.main();
    }

    // Constructor
    function WebsiteSaveGrading() {
        var self = this;
    };

    WebsiteSaveGrading.prototype.main = function () {
        var self = this;
        self.get_users();
        self.saveGrading();
    };

    WebsiteSaveGrading.prototype.get_users = function () {
        var select = $('[data-region="user-selector"]').find('[data-action=change-user]');
        var websiteid = select.attr('data-websiteid');
        var groupid = select.attr('data-groupid');

        Ajax.call([{
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

    WebsiteSaveGrading.prototype._setUsers = function (users) {
        this.users = JSON.parse(users.users);
    };

    WebsiteSaveGrading.prototype.saveGrading = function () {
        var self = this;
        var buttonpressed;

        $('input[name="savechanges"').click(function () {
            buttonpressed = $(this).attr('name');
        });

        $('input[name="saveandshownext"').click(function () {
            buttonpressed = $(this).attr('name');
        });

        $("#gradeform").on('submit', function (e, navigation) {

            var gradeval = parseFloat($('input').first().val());

            if (isNaN(gradeval) || gradeval > 100 || gradeval < 0) {
                $("#id_error_grade").removeAttr('hidden');
                e.stopImmediatePropagation();
                return false;
            } else {
                $("#id_error_grade").attr('hidden', true);
                $('[data-region="overlay"]').show();
            }
            e.preventDefault();

            var grade = {
                userid: $('[data-region="user-info"]').attr('data-userid'), //data returns old values. Use attr instead
                websiteid: String($('[data-region="user-info"]').data('websiteid')),
                courseid: String($('[data-region="website-info"]').data('courseid')),
                formdata: $(this).serialize()
            };

            Ajax.call([{
                methodname: 'mod_website_save_quick_grading',
                args: {
                    grade: JSON.stringify(grade),
                },
                done: self._handleFormSubmissionResponse.bind(this, buttonpressed, navigation),
                fail: function (reason) {
                    Log.error(reason);
                }
            }]);
        });

    };

    WebsiteSaveGrading.prototype._handleFormSubmissionResponse = function (formdata, nav) {
        var nextUserId;
        var currentSelectionid = $('.custom-select option:selected').val();
        var lastSelectElement = $('select.custom-select option:last-child').val();
        var isLast = (currentSelectionid == lastSelectElement);

        if (nav != undefined) {
            if (nav.userid != undefined) {
                nextUserId = nav.userid;
            } else if (nav.direction.includes('right')) {
                nextUserId = $('select.custom-select option:selected').next().val();
                $('.custom-select option:selected').next().attr('selected', 'selected');
            } else if (nav.direction.includes('left')) {
                $('.custom-select option:selected').prev().attr('selected', 'selected');
                nextUserId = $("select.custom-select option").filter(":selected").val();
            }
        } else { // clicked on save and show next
            nextUserId = $('select.custom-select option:selected').next().val();
        }

        if (nextUserId > 0) {

            if (formdata != undefined && formdata == 'savechanges') {
                if ($('.grade-input').val() != '' || $('.grade-input').val() != '0.00') {
                    $('span.gradedtag').removeAttr('hidden');
                    Checker.saveFormState('#gradeform'); // Save new form state.
                }
            } else {
                if (nextUserId != undefined && nextUserId > 0) { // it's coming from the navigation
                    $(`select.custom-select option[value='${currentSelectionid}']`).removeAttr('selected');
                    $(`select.custom-select option[value='${nextUserId}']`).attr('selected', 'selected'); // chance selection
                    WebsiteSaveGrading.prototype.get_next_user(nextUserId, nav);
                } else if (nextUserId == 0) {
                    $(document).trigger('user-changed', nextUserId);
                }
            }
        } else if (nextUserId != 0 && nav != undefined) { // clicked on a link 
            window.open(nav.direction, "_self");
        } else {
            Checker.saveFormState('#gradeform'); // Save new form state          
            if (isLast && !(formdata != undefined && formdata == 'savechanges')) {
                $(`select.custom-select option[value='${currentSelectionid}']`).removeAttr('selected');
                nextUserId = 0;
                $("select.custom-select option[value='0']").attr('selected', 'selected');
                $(document).trigger('user-changed', nextUserId);
                $("div#grading-panel-container").css('display', 'none');
            }

        }

        $('[data-region="overlay"]').hide();
    };

    WebsiteSaveGrading.prototype.get_next_user = function (nextuserid, nav) {

        var websiteid = String($('[data-region="user-info"]').data('websiteid'))

        Ajax.call([{
            methodname: 'mod_website_get_next_participant_details',
            args: {
                userid: nextuserid,
                websiteid: websiteid
            },
            done: function (response) {
                Log.debug(('Grade values retrieved successfuly.'));
                $(document).trigger('user-changed', nextuserid); // Refresh name
                var url = new URL(window.location); //refresh url
                url.searchParams.get('userid');
                url.searchParams.set('userid', nextuserid);
                // We do this so a browser refresh will return to the same user.
                window.history.replaceState({}, "", url);

                WebsiteSaveGrading.prototype.refreshGradePanel(response.html);
            },
            fail: function (reason) {
                Log.error('mod_website_get_participant_by_id. Unable to get elements');
                Log.debug(reason);
            }
        }]);
    };

    WebsiteSaveGrading.prototype.refreshGradePanel = function (htmlResult) {
        var region = $('[data-region="grade"]');

        region.fadeOut(300, function () {
            region.replaceWith(htmlResult);
            region.show();
            $("#gradeform").on('submit', WebsiteSaveGrading.prototype.saveGrading()); //reattach the event
            Checker.saveFormState('#gradeform');
        });
    };


    return {
        init: init
    };
});