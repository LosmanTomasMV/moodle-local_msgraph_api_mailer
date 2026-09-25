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
 * Test and validate page for MS Graph API Mailer.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/msgraph_api_mailer/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(
    get_string('pluginname', 'local_msgraph_api_mailer') . ' - ' .
    get_string('test_validate', 'local_msgraph_api_mailer')
);
$PAGE->set_heading(get_string('pluginname', 'local_msgraph_api_mailer'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->js_call_amd('local_msgraph_api_mailer/index', 'init');

// Read current configuration.
$enabled           = (bool) get_config('local_msgraph_api_mailer', 'enabled');
$tenantid          = get_config('local_msgraph_api_mailer', 'tenant_id');
$clientid          = get_config('local_msgraph_api_mailer', 'client_id');
$clientsecret      = !empty($CFG->local_msgraph_api_mailer_client_secret)
    ? (string) $CFG->local_msgraph_api_mailer_client_secret
    : get_config('local_msgraph_api_mailer', 'client_secret');
$senderemail       = get_config('local_msgraph_api_mailer', 'sender_email');
$senderdisplayname = trim((string) get_config('local_msgraph_api_mailer', 'sender_display_name'));
$isconfigured      = !empty($tenantid) && !empty($clientid) && !empty($clientsecret) && !empty($senderemail);

$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_msgraph_api_mailer']);
$emaillogurl = new moodle_url('/local/msgraph_api_mailer/emaillog.php');

// Build template data.
$templatedata = [
    'settings_url'              => $settingsurl->out(false),
    'emaillog_url'              => $emaillogurl->out(false),
    'not_configured'            => !$isconfigured,
    'missing_config_msg'        => get_string('missing_config', 'local_msgraph_api_mailer'),
    'go_to_settings'            => get_string('go_to_settings', 'local_msgraph_api_mailer'),
    'enabled_badge_class'       => $enabled ? 'ok' : 'warn',
    'enabled_icon'              => $enabled ? 'check' : 'pause',
    'enabled_text'              => $enabled
                                   ? get_string('plugin_enabled', 'local_msgraph_api_mailer')
                                   : get_string('plugin_disabled', 'local_msgraph_api_mailer'),
    'config_badge_class'        => $isconfigured ? 'ok' : 'err',
    'config_icon'               => $isconfigured ? 'check' : 'times',
    'config_text'               => $isconfigured
                                   ? get_string('config_complete', 'local_msgraph_api_mailer')
                                   : get_string('config_incomplete', 'local_msgraph_api_mailer'),
    'has_sender_email'          => !empty($senderemail),
    'sender_email'              => $senderemail,
    'has_display_name'          => !empty($senderdisplayname),
    'sender_display_name'       => $senderdisplayname,
    'sender_display_name_not_set' => get_string('sender_display_name_not_set', 'local_msgraph_api_mailer'),
    'button_disabled'           => !$isconfigured,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_msgraph_api_mailer/test_validate', $templatedata);
echo $OUTPUT->footer();
