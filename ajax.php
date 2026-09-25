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
 * AJAX handler for MS Graph API Mailer admin actions.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

require_once(__DIR__ . '/lib.php');

header('Content-Type: application/json');

// Require login and site config capability.
require_login();
if (!has_capability('moodle/site:config', context_system::instance())) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    die();
}

// Verify session key.
if (!confirm_sesskey()) {
    echo json_encode(['success' => false, 'message' => 'Invalid session key']);
    die();
}

$action = optional_param('action', '', PARAM_TEXT);

try {
    switch ($action) {
        case 'check_permissions':
            echo json_encode(check_graph_permissions());
            break;

        case 'get_connection_status':
            echo json_encode(get_graph_connection_status());
            break;

        case 'send_test_email':
            $email = optional_param('email', '', PARAM_EMAIL);
            echo json_encode(send_graph_test_email($email));
            break;

        case 'send_test_email_attachment':
            $email = optional_param('email', '', PARAM_EMAIL);
            echo json_encode(send_graph_test_email_attachment($email));
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Test OAuth2 token retrieval only.
 *
 * A successful token request proves the tenant/client credentials are usable.
 * It does NOT prove Mail.Send or Exchange App RBAC access to the configured sender.
 *
 * @package local_msgraph_api_mailer
 * @return array Result array with 'success' and 'message' keys.
 */
function check_graph_permissions() {
    $tenantid     = get_config('local_msgraph_api_mailer', 'tenant_id');
    $clientid     = get_config('local_msgraph_api_mailer', 'client_id');
    $clientsecret = get_config('local_msgraph_api_mailer', 'client_secret');
    $senderemail  = get_config('local_msgraph_api_mailer', 'sender_email');

    if (empty($tenantid) || empty($clientid) || empty($clientsecret) || empty($senderemail)) {
        return ['success' => false, 'message' => get_string('missing_config', 'local_msgraph_api_mailer')];
    }

    try {
        $client = new \local_msgraph_api_mailer\api\graph_client();
        $result = $client->test_connection();

        if ($result['success']) {
            return ['success' => true, 'message' => get_string('oauth_check_success', 'local_msgraph_api_mailer')];
        }
        return [
            'success' => false,
            'message' => get_string('oauth_check_failed', 'local_msgraph_api_mailer') . ' ' . $result['message'],
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => get_string('oauth_check_failed', 'local_msgraph_api_mailer') . ' ' . $e->getMessage(),
        ];
    }
}

/**
 * Lightweight connection status check for the auto-loading badge on the settings page.
 * Returns connected bool + short label instead of full message.
 *
 * @package local_msgraph_api_mailer
 * @return array Result array with 'connected' and 'message' keys.
 */
function get_graph_connection_status() {
    $result = check_graph_permissions();
    return [
        'connected' => $result['success'],
        'message'   => $result['message'],
    ];
}

/**
 * Send a test email (no attachment) via Graph API.
 *
 * @package local_msgraph_api_mailer
 * @param string $email Recipient email address.
 * @return array Result array with 'success' and 'message' keys.
 */
function send_graph_test_email($email) {
    global $CFG;

    if (empty($email)) {
        return ['success' => false, 'message' => get_string('test_email_address', 'local_msgraph_api_mailer') . ' is required'];
    }

    $senderemail     = get_config('local_msgraph_api_mailer', 'sender_email');
    $displaynamecfg  = trim((string) get_config('local_msgraph_api_mailer', 'sender_display_name'));

    if (empty($senderemail)) {
        return ['success' => false, 'message' => get_string('missing_config', 'local_msgraph_api_mailer')];
    }

    $subject = 'Test Email from MS Graph Mailer';
    $body    = '<h2>Test Email</h2>
        <p>This is a test email sent via Microsoft Graph API from Moodle.</p>
        <p><strong>Plugin:</strong> MS Graph Mailer</p>
        <p><strong>Sender Email:</strong> ' . htmlspecialchars($senderemail, ENT_QUOTES) . '</p>
        <p><strong>Sender Display Name (configured):</strong> ' .
        ($displaynamecfg !== '' ? htmlspecialchars($displaynamecfg, ENT_QUOTES) : '<em>Not configured</em>') . '</p>
        <p><strong>Moodle Version:</strong> ' . $CFG->release . '</p>
        <p><strong>PHP Version:</strong> ' . PHP_VERSION . '</p>
        <p><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</p>';

    try {
        $client = new \local_msgraph_api_mailer\api\graph_client();
        $result = $client->send_email($email, $subject, $body);

        if ($result['success']) {
            local_msgraph_api_mailer_log_record([$email], $subject, 1, 'Test email sent successfully');
            return ['success' => true, 'message' => get_string('email_sent_success', 'local_msgraph_api_mailer')];
        }

        $errormsg = 'HTTP ' . $result['http_code'] . ' -- ' . substr($result['response'], 0, 500);
        local_msgraph_api_mailer_log_record([$email], $subject, 0, $errormsg);
        return [
            'success' => false,
            'message' => get_string('email_sent_failed', 'local_msgraph_api_mailer') . ' HTTP ' . $result['http_code']
                . (!empty($result['response']) ? ' — ' . substr(strip_tags($result['response']), 0, 200) : ''),
        ];
    } catch (Exception $e) {
        local_msgraph_api_mailer_log_record([$email], $subject, 0, $e->getMessage());
        return [
            'success' => false,
            'message' => get_string('email_sent_failed', 'local_msgraph_api_mailer') . ' ' . $e->getMessage(),
        ];
    }
}

/**
 * Send a test email WITH a real .xlsx attachment via Graph API.
 * The attachment is a temp file with no extension — exactly like Moodle scheduled reports.
 *
 * @package local_msgraph_api_mailer
 * @param string $email Recipient email address.
 * @return array Result array with 'success' and 'message' keys.
 */
function send_graph_test_email_attachment($email) {
    if (empty($email)) {
        return ['success' => false, 'message' => get_string('test_email_address', 'local_msgraph_api_mailer') . ' is required'];
    }

    $senderemail = get_config('local_msgraph_api_mailer', 'sender_email');
    if (empty($senderemail)) {
        return ['success' => false, 'message' => get_string('missing_config', 'local_msgraph_api_mailer')];
    }

    // Build a minimal xlsx (ZIP + OOXML) in a temp file without extension.
    $tmpfile = build_test_xlsx();
    if (!$tmpfile) {
        return ['success' => false, 'message' => 'Could not create test xlsx (ZipArchive required).'];
    }

    $subject = 'Test Email with Attachment — MS Graph Mailer';
    $body    = '<h2>Attachment Test</h2>
        <p>This test email verifies that file attachments are delivered correctly.</p>
        <ul>
          <li><strong>Temp filename:</strong> ' . htmlspecialchars(basename($tmpfile), ENT_QUOTES) . ' (no extension)</li>
          <li><strong>Expected attachment name:</strong> Test Report.xlsx</li>
          <li><strong>MIME detection:</strong> magic bytes (PK ZIP + xl/workbook marker)</li>
        </ul>
        <p>If you can open the attached <code>Test Report.xlsx</code>, the attachment pipeline is working correctly.</p>
        <p><strong>Sent:</strong> ' . date('Y-m-d H:i:s') . '</p>';

    // Mimic exactly what Moodle scheduler produces:
    // - filepath = temp path without extension.
    // - filename  = display name without extension.
    // - mimetype  = application/octet-stream (mimeinfo result for extensionless file).
    $attachments = [[
        'filepath' => $tmpfile,
        'filename' => 'Test Report',
        'mimetype' => 'application/octet-stream',
        'isstring' => false,
    ]];

    try {
        $client = new \local_msgraph_api_mailer\api\graph_client();
        $result = $client->send_email($email, $subject, $body, null, $attachments);

        @unlink($tmpfile);

        if ($result['success']) {
            local_msgraph_api_mailer_log_record([$email], $subject, 1, 'Test with attachment sent successfully', 1);
            return ['success' => true, 'message' => get_string('email_attachment_sent_success', 'local_msgraph_api_mailer')];
        }

        $errormsg = 'HTTP ' . $result['http_code'] . ' -- ' . substr($result['response'], 0, 500);
        local_msgraph_api_mailer_log_record([$email], $subject, 0, $errormsg, 1);
        return [
            'success' => false,
            'message' => get_string('email_sent_failed', 'local_msgraph_api_mailer') . ' HTTP ' . $result['http_code']
                . (!empty($result['response']) ? ' — ' . substr(strip_tags($result['response']), 0, 200) : ''),
        ];
    } catch (Exception $e) {
        @unlink($tmpfile);
        local_msgraph_api_mailer_log_record([$email], $subject, 0, $e->getMessage(), 1);
        return [
            'success' => false,
            'message' => get_string('email_sent_failed', 'local_msgraph_api_mailer') . ' ' . $e->getMessage(),
        ];
    }
}

/**
 * Build a minimal valid .xlsx file in a temp path WITHOUT extension.
 * Simulates the temp files produced by Moodle's scheduled report delivery.
 *
 * @package local_msgraph_api_mailer
 * @return string|false Temp file path on success, false on failure.
 */
function build_test_xlsx() {
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'tempup_');
    unlink($tmp); // Remove so ZipArchive can create a clean file.

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE) !== true) {
        return false;
    }

    $zip->addFromString(
        '[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml"' .
        ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet1.xml"' .
        ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
        '</Types>'
    );

    $zip->addFromString(
        '_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1"' .
        ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"' .
        ' Target="xl/workbook.xml"/>' .
        '</Relationships>'
    );

    $zip->addFromString(
        'xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"' .
        ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheets><sheet name="Test Report" sheetId="1" r:id="rId1"/></sheets>' .
        '</workbook>'
    );

    $zip->addFromString(
        'xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1"' .
        ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"' .
        ' Target="worksheets/sheet1.xml"/>' .
        '</Relationships>'
    );

    $zip->addFromString(
        'xl/worksheets/sheet1.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' .
        '<row r="1">' .
        '<c r="A1" t="inlineStr"><is><t>Name</t></is></c>' .
        '<c r="B1" t="inlineStr"><is><t>Course</t></is></c>' .
        '<c r="C1" t="inlineStr"><is><t>Status</t></is></c>' .
        '</row>' .
        '<row r="2">' .
        '<c r="A2" t="inlineStr"><is><t>Test User</t></is></c>' .
        '<c r="B2" t="inlineStr"><is><t>Sample Course</t></is></c>' .
        '<c r="C2" t="inlineStr"><is><t>Completed</t></is></c>' .
        '</row>' .
        '</sheetData></worksheet>'
    );

    $zip->close();

    return file_exists($tmp) ? $tmp : false;
}
