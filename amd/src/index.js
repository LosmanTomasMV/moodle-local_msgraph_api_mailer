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
 * MS Graph Mailer - Standalone test/validate page JS.
 *
 * @module     local_msgraph_api_mailer/index
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax'], function($, Ajax) {
    'use strict';

    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    /**
     * Show a result alert in the given div.
     *
     * @param {string}  divId   Target div ID (without #)
     * @param {boolean} success True for success style, false for error style
     * @param {string}  message Message to display inside the alert
     */
    function showResult(divId, success, message) {
        const cls = success ? 'alert-success' : 'alert-danger';
        $('#' + divId).html('<div class="alert ' + cls + '">' + message + '</div>');
    }

    return {

        /**
         * Initialise click handlers for the test/validate page buttons.
         */
        init: function() {

            $('#check-permissions-btn').on('click', function() {
                $('#permission-result').html(
                    '<div class="alert alert-info">' +
                    '<i class="fa fa-spinner fa-spin"></i> Checking OAuth credentials...</div>'
                );
                Ajax.call([{
                    methodname: 'local_msgraph_api_mailer_check_permissions',
                    args: {}
                }])[0].then(function(r) {
                    showResult('permission-result', r.success, r.message);
                    return undefined;
                }).catch(function(e) {
                    showResult('permission-result', false, e?.message ?? 'Error connecting to server.');
                });
            });

            /**
             * Validate email input then call the external service.
             *
             * @param {string} methodname External service method name
             * @param {string} label      Spinner label shown while sending
             */
            function sendEmail(methodname, label) {
                const email = $('#test-email-input').val().trim();
                if (!email) {
                    $('#email-result').html(
                        '<div class="alert alert-warning">Please enter an email address.</div>'
                    );
                    return;
                }
                if (!emailRe.test(email)) {
                    $('#email-result').html(
                        '<div class="alert alert-warning">Please enter a valid email address.</div>'
                    );
                    return;
                }
                $('#email-result').html(
                    '<div class="alert alert-info">' +
                    '<i class="fa fa-spinner fa-spin"></i> ' + label + '</div>'
                );
                Ajax.call([{
                    methodname: methodname,
                    args: {email: email}
                }])[0].then(function(r) {
                    showResult('email-result', r.success, r.message);
                    return undefined;
                }).catch(function(e) {
                    showResult('email-result', false, e?.message ?? 'Error connecting to server.');
                });
            }

            $('#send-test-btn').on('click', function() {
                sendEmail(
                    'local_msgraph_api_mailer_send_test_email',
                    'Sending test email...'
                );
            });

            $('#send-test-attachment-btn').on('click', function() {
                sendEmail(
                    'local_msgraph_api_mailer_send_test_email_attachment',
                    'Sending test email with attachment...'
                );
            });

            $('#test-email-input').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#send-test-btn').trigger('click');
                }
            });
        }
    };
});
