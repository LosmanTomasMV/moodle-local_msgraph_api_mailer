<?php
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
 * Admin settings for MS Graph API Mailer.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Custom admin setting classes with placeholder support.
require_once(__DIR__ . '/classes/admin/configtext_placeholder.php');
require_once(__DIR__ . '/classes/admin/configpassword_placeholder.php');
require_once(__DIR__ . '/classes/admin/configcheckbox_with_required.php');

use local_msgraph_api_mailer\admin\configtext_placeholder;
use local_msgraph_api_mailer\admin\configpassword_placeholder;
use local_msgraph_api_mailer\admin\configcheckbox_with_required;

if (!isset($hassiteconfig) || $hassiteconfig) {
    $settings = new admin_settingpage('local_msgraph_api_mailer', get_string('pluginname', 'local_msgraph_api_mailer'));
    $ADMIN->add('localplugins', $settings);

    // Architectural notice — explains the core file patch before any configuration.
    $settings->add(new admin_setting_heading(
        'local_msgraph_api_mailer_arch_heading',
        get_string('arch_heading', 'local_msgraph_api_mailer'),
        get_string('arch_desc', 'local_msgraph_api_mailer')
    ));

    // Live patch status badge — reads the value stored by the after_config hook
    // (which already ran earlier in this same request).
    $patchstatus = get_config('local_msgraph_api_mailer', 'patch_status') ?: 'unknown';
    $statusmap = [
        'ok' => ['success', 'check-circle'],
        'manual_required' => ['warning', 'exclamation-triangle'],
        'failed_readonly' => ['danger', 'times-circle'],
        'failed_anchor' => ['danger', 'times-circle'],
        'unsupported_core' => ['danger', 'times-circle'],
        'failed_unknown' => ['danger', 'times-circle'],
        'not_readable' => ['danger', 'times-circle'],
        'unknown' => ['info', 'info-circle'],
    ];
    [$alerttype, $icon] = $statusmap[$patchstatus] ?? ['info', 'info-circle'];
    $langkey = array_key_exists($patchstatus, $statusmap) ? 'patch_status_' . $patchstatus : 'patch_status_unknown';
    $patchhtml = html_writer::div(
        html_writer::tag('i', '', ['class' => 'fa fa-' . $icon . ' me-1']) .
        get_string($langkey, 'local_msgraph_api_mailer'),
        'alert alert-' . $alerttype . ' mt-2 mb-0'
    );
    $settings->add(new admin_setting_description(
        'local_msgraph_api_mailer_patch_status',
        get_string('patch_status_label', 'local_msgraph_api_mailer'),
        $patchhtml
    ));

    $settings->add(new configcheckbox_with_required(
        'local_msgraph_api_mailer/enabled',
        get_string('enabled', 'local_msgraph_api_mailer'),
        get_string('enabled_desc', 'local_msgraph_api_mailer'),
        0,
        ['tenant_id', 'client_id', 'client_secret', 'sender_email'],
        get_string('enabled_requires_config', 'local_msgraph_api_mailer')
    ));

    $settings->add(new configtext_placeholder(
        'local_msgraph_api_mailer/tenant_id',
        get_string('tenant_id', 'local_msgraph_api_mailer'),
        get_string('tenant_id_desc', 'local_msgraph_api_mailer'),
        '',
        PARAM_TEXT,
        null,
        'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'
    ));

    $settings->add(new configtext_placeholder(
        'local_msgraph_api_mailer/client_id',
        get_string('client_id', 'local_msgraph_api_mailer'),
        get_string('client_id_desc', 'local_msgraph_api_mailer'),
        '',
        PARAM_TEXT,
        null,
        'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'
    ));

    $settings->add(new configpassword_placeholder(
        'local_msgraph_api_mailer/client_secret',
        get_string('client_secret', 'local_msgraph_api_mailer'),
        get_string('client_secret_desc', 'local_msgraph_api_mailer'),
        '',
        get_string('client_secret_placeholder', 'local_msgraph_api_mailer')
    ));

    $settings->add(new configtext_placeholder(
        'local_msgraph_api_mailer/sender_email',
        get_string('sender_email', 'local_msgraph_api_mailer'),
        get_string('sender_email_desc', 'local_msgraph_api_mailer'),
        '',
        PARAM_EMAIL,
        null,
        'no-reply@yourdomain.com'
    ));

    $settings->add(new configtext_placeholder(
        'local_msgraph_api_mailer/sender_display_name',
        get_string('sender_display_name', 'local_msgraph_api_mailer'),
        get_string('sender_display_name_desc', 'local_msgraph_api_mailer'),
        '',
        PARAM_TEXT,
        null,
        get_string('sender_display_name_placeholder', 'local_msgraph_api_mailer')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_msgraph_api_mailer/log_emails',
        get_string('log_emails', 'local_msgraph_api_mailer'),
        get_string('log_emails_desc', 'local_msgraph_api_mailer'),
        1
    ));

    $settings->add(new admin_setting_configduration(
        'local_msgraph_api_mailer/log_retention',
        get_string('log_retention', 'local_msgraph_api_mailer'),
        get_string('log_retention_desc', 'local_msgraph_api_mailer'),
        90 * DAYSECS
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_msgraph_api_mailer/fallback_smtp',
        get_string('fallback_smtp', 'local_msgraph_api_mailer'),
        get_string('fallback_smtp_desc', 'local_msgraph_api_mailer'),
        0
    ));

    // Test and Validate section.
    $settings->add(new admin_setting_heading(
        'local_msgraph_api_mailer_test_heading',
        get_string('test_email', 'local_msgraph_api_mailer'),
        get_string('test_email_desc', 'local_msgraph_api_mailer')
    ));

    // Test email address (used by the inline AJAX buttons below).
    $settings->add(new configtext_placeholder(
        'local_msgraph_api_mailer/test_email_temp',
        get_string('test_email_address', 'local_msgraph_api_mailer'),
        get_string('test_email_address_desc', 'local_msgraph_api_mailer'),
        '',
        PARAM_EMAIL,
        null,
        'user@example.com'
    ));

    // Links: test page, email log page, and changelog.
    $testpageurl      = new moodle_url('/local/msgraph_api_mailer/index.php');
    $logpageurl       = new moodle_url('/local/msgraph_api_mailer/emaillog.php');
    $changelogpageurl = new moodle_url('/local/msgraph_api_mailer/changelog.php');
    $settings->add(new admin_setting_description(
        'local_msgraph_api_mailer_testpage_link',
        '',
        html_writer::tag(
            'a',
            html_writer::tag('i', '', ['class' => 'fa fa-external-link']) . ' ' .
            get_string('open_test_page', 'local_msgraph_api_mailer'),
            ['href' => $testpageurl->out(false), 'class' => 'btn btn-info me-2', 'target' => '_blank']
        ) .
        html_writer::tag(
            'a',
            html_writer::tag('i', '', ['class' => 'fa fa-list']) . ' ' .
            get_string('view_email_logs', 'local_msgraph_api_mailer'),
            ['href' => $logpageurl->out(false), 'class' => 'btn btn-secondary me-2', 'target' => '_blank']
        ) .
        html_writer::tag(
            'a',
            html_writer::tag('i', '', ['class' => 'fa fa-history']) . ' ' .
            get_string('view_changelog', 'local_msgraph_api_mailer'),
            ['href' => $changelogpageurl->out(false), 'class' => 'btn btn-outline-secondary', 'target' => '_blank']
        ) .
        html_writer::tag('p', get_string('quick_test_desc', 'local_msgraph_api_mailer'), ['class' => 'mt-3 mb-1 text-muted small'])
    ));

    // Inline AJAX buttons — only load JS when this exact settings page is rendered.
    // $ADMIN->fulltree is true on admin/index.php too, so we also check the section param.
    if ($ADMIN->fulltree) {
        $currentsection = optional_param('section', '', PARAM_ALPHANUMEXT);
        if ($currentsection === 'local_msgraph_api_mailer') {
            global $PAGE;
            $PAGE->requires->js_call_amd('local_msgraph_api_mailer/tester', 'init');
        }
    }
}
