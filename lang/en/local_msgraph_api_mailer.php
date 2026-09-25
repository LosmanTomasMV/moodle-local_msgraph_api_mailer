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
 * Language strings for MS Graph API Mailer.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['arch_desc'] = 'This plugin intercepts Moodle outgoing emails by adding a small hook to lib/phpmailer/moodle_phpmailer.php. The school production fork never applies or re-applies this core change automatically. After installation and after each Moodle upgrade, review compatibility and use the CLI patch tool explicitly. The current patch status is shown below.';
$string['arch_heading'] = 'How This Plugin Intercepts Email';
$string['back_to_settings'] = 'Back to Settings';
$string['changelog_desc'] = 'Version history for MS Graph API Mailer';
$string['changelog_title'] = 'Changelog';
$string['check_permissions'] = 'Check OAuth credentials';
$string['check_permissions_btn'] = 'Check OAuth credentials';
$string['check_permissions_desc'] = 'Verify that the configured tenant ID, client ID and client secret can obtain an OAuth access token. This does not verify Mail.Send or Exchange App RBAC.';
$string['client_id'] = 'Client ID';
$string['client_id_desc'] = 'Application (client) ID from Azure AD';
$string['client_secret'] = 'Client Secret';
$string['client_secret_desc'] = 'Client secret from Azure AD';
$string['client_secret_placeholder'] = 'Enter your Azure AD client secret value';
$string['config_complete'] = 'All Azure settings configured';
$string['config_incomplete'] = 'Azure settings incomplete';
$string['config_status'] = 'Configuration Status';
$string['connected'] = 'OAuth credentials valid';
$string['connection_badge_connected'] = 'OAuth valid';
$string['connection_badge_disconnected'] = 'OAuth failed';
$string['connection_status'] = 'OAuth Credential Status';
$string['connection_status_desc'] = 'Verify that the configured Microsoft Entra credentials can obtain an OAuth access token. Use Send Test Email to verify Mail.Send and sender mailbox authorization.';
$string['disconnected'] = 'Not connected';
$string['email_attachment_sent_success'] = '&#10003; Test email with attachment sent! Check your inbox for Test Report.xlsx.';
$string['email_log_title'] = 'Email Log';
$string['email_logs'] = 'Email Logs';
$string['email_sent_failed'] = '&#10007; Failed to send test email.';
$string['email_sent_success'] = '&#10003; Test email sent successfully!';
$string['enabled'] = 'Enable MS Graph Mailer';
$string['enabled_desc'] = 'Route all Moodle emails through Microsoft Graph API';
$string['enabled_requires_config'] = 'Please fill in Azure Tenant ID, Client ID, Client Secret, and Sender Email Address for enabling MS Graph API Mailer.';
$string['error'] = 'Error';
$string['fallback_smtp'] = 'Fallback to SMTP on failure';
$string['fallback_smtp_desc'] = 'Use SMTP if Microsoft Graph API fails';
$string['go_to_settings'] = 'Go to Settings';
$string['log_btn_clear'] = 'Clear';
$string['log_btn_export'] = 'Export CSV';
$string['log_btn_filter'] = 'Filter';
$string['log_col_attachment'] = 'Attachment';
$string['log_col_datetime'] = 'Date / Time';
$string['log_col_recipient'] = 'Recipient(s)';
$string['log_col_response'] = 'Response';
$string['log_col_status'] = 'Status';
$string['log_col_subject'] = 'Subject';
$string['log_emails'] = 'Log sent emails';
$string['log_emails_desc'] = 'Store a record of every email sent (or failed) in the database for audit purposes';
$string['log_filter_from'] = 'From date';
$string['log_filter_heading'] = 'Filters';
$string['log_filter_to'] = 'To date';
$string['log_no_records'] = 'No records found matching the current filters.';
$string['log_records_count'] = 'records';
$string['log_retention'] = 'Email log retention';
$string['log_retention_desc'] = 'Automatically delete mail log records older than this period. Default: 90 days.';
$string['log_status_all'] = 'All';
$string['log_status_failed'] = 'Failed';
$string['log_status_sent'] = 'Accepted';
$string['log_subject_placeholder'] = 'Search subject...';
$string['missing_config'] = 'Please configure all Azure settings before testing.';
$string['open_test_page'] = 'Open Test & Validate Page';
$string['patch_status_failed_anchor'] = '&#10007; Patch failed: Moodle has changed the structure of lib/phpmailer/moodle_phpmailer.php. Emails are NOT being intercepted. Please update the MS Graph API Mailer plugin or raise an issue with the developer.';
$string['patch_status_failed_readonly'] = '&#10007; Patch could not be applied: lib/phpmailer/moodle_phpmailer.php is read-only. Emails are NOT being intercepted. This is expected on immutable deployments (read-only Docker containers, etc.). You must make the file writable or pre-apply the patch in your deployment pipeline.';
$string['patch_status_failed_unknown'] = '&#10007; Patch failed for an unknown reason. Emails may NOT be intercepted. Check that lib/phpmailer/moodle_phpmailer.php is writable and reload this page.';
$string['patch_status_label'] = 'Core Patch Status';
$string['patch_status_not_readable'] = '&#10007; lib/phpmailer/moodle_phpmailer.php is not readable. Patch status cannot be determined. Emails may NOT be intercepted.';
$string['patch_status_ok'] = '&#10003; Patch is applied — outgoing Moodle email can be intercepted by this plugin.';
$string['patch_status_manual_required'] = '&#9888; Core patch is not applied. Review Moodle compatibility, then run cli/patch.php --apply explicitly.';
$string['patch_status_unknown'] = 'Patch status not yet determined. It will be checked on the next page load.';
$string['oauth_check_failed'] = '&#10007; OAuth credential check failed. Check the tenant ID, client ID and client secret.';
$string['oauth_check_success'] = '&#10003; OAuth credentials are valid and an access token was obtained. Mail.Send and sender authorization are not verified by this check.';
$string['plugin_disabled'] = 'Plugin Disabled';
$string['plugin_enabled'] = 'Plugin Enabled';
$string['pluginname'] = 'MS Graph API Mailer';
$string['privacy:metadata:local_msgraph_api_mailer_log'] = 'The MS Graph API Mailer plugin stores a log of every outbound email it processes. Each row includes the recipient addresses and subject, which may constitute personal data.';
$string['privacy:metadata:local_msgraph_api_mailer_log:has_attachment'] = 'Whether the email included one or more file attachments.';
$string['privacy:metadata:local_msgraph_api_mailer_log:recipients'] = 'The JSON-encoded list of recipient email addresses for the outbound email.';
$string['privacy:metadata:local_msgraph_api_mailer_log:response'] = 'A sanitised delivery status or error summary for this email. Raw Microsoft Graph response bodies are not stored by the school production fork.';
$string['privacy:metadata:local_msgraph_api_mailer_log:status'] = 'Whether Microsoft Graph accepted the email for processing (1) or the send attempt failed (0).';
$string['privacy:metadata:local_msgraph_api_mailer_log:subject'] = 'The subject line of the outbound email.';
$string['privacy:metadata:local_msgraph_api_mailer_log:timecreated'] = 'The Unix timestamp at which this log entry was created.';
$string['quick_test_desc'] = 'Quick test from this page:';
$string['recipient_email'] = 'Recipient Email Address';
$string['send_test_email'] = 'Send Test Email';
$string['send_test_email_btn'] = 'Send Test Email';
$string['send_test_email_desc'] = 'Send a test email to verify the complete email delivery pipeline.';
$string['send_test_with_attachment_btn'] = 'Send Test with Attachment';
$string['sender_display_name'] = 'Sender Display Name';
$string['sender_display_name_desc'] = 'Display name shown to recipients in the From field (e.g. "Moodle Notifications"). Note: Outlook users in the same Microsoft 365 organisation may see the Azure AD account name instead - test by sending to an external address (Gmail, etc.) to confirm it is working.';
$string['sender_display_name_not_set'] = 'No display name set';
$string['sender_display_name_placeholder'] = 'Moodle Notifications';
$string['sender_email'] = 'Sender Email Address';
$string['sender_email_desc'] = 'Email address from Microsoft 365 that will send emails';
$string['settings_saved'] = 'Settings saved successfully';
$string['status'] = 'OAuth Credential Status';
$string['status_badge_checking'] = 'Checking OAuth credentials...';
$string['tenant_id'] = 'Azure Tenant ID';
$string['tenant_id_desc'] = 'Your Microsoft Entra (Azure AD) Tenant ID';
$string['test_email'] = 'Test Email';
$string['test_email_address'] = 'Test Email Address';
$string['test_email_address_desc'] = 'Enter an email address to send a test email to';
$string['test_email_desc'] = 'Send a test email to verify configuration';
$string['test_result'] = 'Test Result';
$string['test_validate'] = 'Test & Validate';
$string['view_changelog'] = 'Changelog';
$string['view_email_logs'] = 'View Email Logs';
$string['task_cleanup_logs'] = 'Delete expired MS Graph mail logs';
