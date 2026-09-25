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
 * MS Graph Mailer - Settings Page Test Functionality.
 *
 * Adds to the settings page:
 *  - OAuth credential status badge (auto-checked on load)
 *  - Check OAuth credentials button
 *  - Send Test Email button
 *  - Send Test with Attachment button
 *
 * @module     local_msgraph_api_mailer/tester
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/str'], function($, Ajax, str) {
    'use strict';

    return {
        strings: {},

        init: function() {
            str.get_strings([
                {key: 'check_permissions_btn', component: 'local_msgraph_api_mailer'},
                {key: 'send_test_email_btn', component: 'local_msgraph_api_mailer'},
                {key: 'test_email_address', component: 'local_msgraph_api_mailer'},
                {key: 'test_result', component: 'local_msgraph_api_mailer'},
                {key: 'send_test_with_attachment_btn', component: 'local_msgraph_api_mailer'},
                {key: 'status_badge_checking', component: 'local_msgraph_api_mailer'},
                {key: 'connection_badge_connected', component: 'local_msgraph_api_mailer'},
                {key: 'connection_badge_disconnected', component: 'local_msgraph_api_mailer'}
            ]).then((s) => {
                this.strings = {
                    checkPermissions:       s[0],
                    sendTestEmail:          s[1],
                    testEmailAddress:       s[2],
                    testResult:             s[3],
                    sendTestWithAttachment: s[4],
                    statusChecking:         s[5],
                    connected:              s[6],
                    disconnected:           s[7]
                };
                return this.addTestSection();
            }).catch(() => {
                this.strings = {
                    checkPermissions:       'Check OAuth credentials',
                    sendTestEmail:          'Send Test Email',
                    testEmailAddress:       'Test Email Address',
                    testResult:             'Test Result',
                    sendTestWithAttachment: 'Send Test with Attachment',
                    statusChecking:         'Checking connection...',
                    connected:              'Connected',
                    disconnected:           'Disconnected'
                };
                this.addTestSection();
            });
        },

        addTestSection: function() {
            if ($('#msgraph-test-buttons').length) {
                return;
            }

            const html =
                '<div id="msgraph-test-buttons" class="msgraph-test-section"' +
                ' style="margin-top:25px;padding:20px;border:2px solid #0078d4;' +
                'border-radius:8px;background:#f8f9fa;">' +

                // Connection status badge.
                '<div id="msgraph-status-row"' +
                ' style="margin-bottom:14px;display:flex;align-items:center;gap:10px;">' +
                '<strong style="font-size:.9rem;">OAuth Credential Status:</strong>' +
                '<span id="msgraph-status-badge"' +
                ' style="display:inline-flex;align-items:center;gap:5px;padding:4px 12px;' +
                'border-radius:20px;font-size:.82rem;font-weight:600;' +
                'background:#e9ecef;color:#495057;">' +
                '<span id="msgraph-status-dot"' +
                ' style="width:8px;height:8px;border-radius:50%;' +
                'background:#adb5bd;display:inline-block;"></span>' +
                '<span id="msgraph-status-text">' + this.strings.statusChecking + '</span>' +
                '</span>' +
                '</div>' +

                // Action buttons.
                '<div class="test-buttons" style="margin-top:6px;display:flex;flex-wrap:wrap;gap:8px;">' +
                '<button type="button" id="check-permissions-btn" class="btn btn-outline-primary btn-sm">' +
                '<i class="fa fa-plug"></i> ' + this.strings.checkPermissions +
                '</button>' +
                '<button type="button" id="send-test-btn" class="btn btn-primary btn-sm">' +
                '<i class="fa fa-envelope"></i> ' + this.strings.sendTestEmail +
                '</button>' +
                '<button type="button" id="send-test-attachment-btn" class="btn btn-secondary btn-sm">' +
                '<i class="fa fa-paperclip"></i> ' + this.strings.sendTestWithAttachment +
                '</button>' +
                '</div>' +

                // Result area.
                '<div id="test-result" style="margin-top:12px;"></div>' +
                '</div>';

            // Insert after the test email input field.
            let inserted = false;
            const $input = $('#id_s_local_msgraph_api_mailer_test_email_temp');
            if ($input.length) {
                $input.closest('.form-group, .fitem').after(html);
                inserted = true;
            }
            if (!inserted) {
                const $form = $('form.mform');
                if ($form.length) {
                    $form.append(html);
                    inserted = true;
                }
            }
            if (!inserted) {
                $('#region-main').append(html);
            }

            this.bindEvents();

            // Auto-check connection status on load.
            this.refreshStatusBadge();
        },

        bindEvents: function() {
            $('#check-permissions-btn').off('click').on('click', () => {
                this.checkPermissions();
            });

            $('#send-test-btn').off('click').on('click', () => {
                this.sendTestEmail(false);
            });

            $('#send-test-attachment-btn').off('click').on('click', () => {
                this.sendTestEmail(true);
            });
        },

        /**
         * Auto-refresh the connection status badge (called on page load).
         */
        refreshStatusBadge: function() {
            this.setBadge('checking');

            Ajax.call([{
                methodname: 'local_msgraph_api_mailer_get_connection_status',
                args: {}
            }])[0].then((r) => {
                this.setBadge(r.connected ? 'connected' : 'disconnected');
                return undefined;
            }).catch(() => {
                this.setBadge('disconnected');
            });
        },

        /**
         * Update the connection badge appearance.
         *
         * @param {string} state  'checking' | 'connected' | 'disconnected'
         */
        setBadge: function(state) {
            const $badge = $('#msgraph-status-badge');
            const $dot = $('#msgraph-status-dot');
            const $text = $('#msgraph-status-text');

            if (state === 'connected') {
                $badge.css({background: '#d4edda', color: '#155724'});
                $dot.css('background', '#28a745');
                $text.text(this.strings.connected);
            } else if (state === 'disconnected') {
                $badge.css({background: '#f8d7da', color: '#721c24'});
                $dot.css('background', '#dc3545');
                $text.text(this.strings.disconnected);
            } else {
                $badge.css({background: '#e9ecef', color: '#495057'});
                $dot.css('background', '#adb5bd');
                $text.text(this.strings.statusChecking);
            }
        },

        /**
         * Check permissions and show detailed result.
         */
        checkPermissions: function() {
            const $result = $('#test-result');
            $result.html('<div class="alert alert-info py-2">Checking OAuth credentials...</div>');

            Ajax.call([{
                methodname: 'local_msgraph_api_mailer_check_permissions',
                args: {}
            }])[0].then((r) => {
                const msg = r.message || 'Unexpected response';
                $result.html(r.success
                    ? '<div class="alert alert-success py-2">' + msg + '</div>'
                    : '<div class="alert alert-danger py-2">' + msg + '</div>');
                this.setBadge(r.success ? 'connected' : 'disconnected');
                return undefined;
            }).catch((e) => {
                $result.html('<div class="alert alert-danger py-2">' + (e?.message ?? 'Server error.') + '</div>');
                this.setBadge('disconnected');
            });
        },

        /**
         * Send a test email, with or without attachment.
         *
         * @param {boolean} withAttachment Whether to include a .xlsx attachment.
         */
        sendTestEmail: function(withAttachment) {
            const $result = $('#test-result');
            const email = $('#id_s_local_msgraph_api_mailer_test_email_temp').val();

            if (!email) {
                $result.html(
                    '<div class="alert alert-warning py-2">' +
                    'Please enter a test email address in the field above.</div>'
                );
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                $result.html(
                    '<div class="alert alert-warning py-2">' +
                    'Please enter a valid email address.</div>'
                );
                return;
            }

            const methodname = withAttachment
                ? 'local_msgraph_api_mailer_send_test_email_attachment'
                : 'local_msgraph_api_mailer_send_test_email';
            const label = withAttachment
                ? 'Sending test email with attachment...'
                : 'Sending test email...';

            $result.html('<div class="alert alert-info py-2">' + label + '</div>');

            Ajax.call([{
                methodname: methodname,
                args: {email: email}
            }])[0].then((r) => {
                $result.html(r.success
                    ? '<div class="alert alert-success py-2">' + r.message + '</div>'
                    : '<div class="alert alert-danger py-2">' + r.message + '</div>');
                return undefined;
            }).catch((e) => {
                $result.html('<div class="alert alert-danger py-2">' + (e?.message ?? 'Server error.') + '</div>');
            });
        }
    };
});
