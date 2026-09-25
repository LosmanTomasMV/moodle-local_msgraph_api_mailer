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
 * Core library functions for MS Graph API Mailer.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Check if MS Graph Mailer is enabled and fully configured.
 *
 * @return bool True if enabled and all required settings are set.
 */
function local_msgraph_api_mailer_is_enabled() {
    if (!get_config('local_msgraph_api_mailer', 'enabled')) {
        return false;
    }
    return !empty(get_config('local_msgraph_api_mailer', 'tenant_id'))
        && !empty(get_config('local_msgraph_api_mailer', 'client_id'))
        && !empty(get_config('local_msgraph_api_mailer', 'client_secret'))
        && !empty(get_config('local_msgraph_api_mailer', 'sender_email'));
}

/**
 * Moodle PHPMailer hook — called by email_to_user() for EVERY outgoing email.
 *
 * Replaces PHPMailer/SMTP delivery with Microsoft Graph API.
 * Respects Moodle's "Email diverting" setting ($CFG->divertallemailsto).
 * All sends (success and failure) are logged unconditionally.
 *
 * @param \PHPMailer\PHPMailer\PHPMailer $mail Fully configured PHPMailer instance.
 */
function local_msgraph_api_mailer_phpmailer_init($mail) {
    if (!local_msgraph_api_mailer_is_enabled()) {
        return; // Plugin disabled or not configured — fall through to SMTP.
    }

    // Preserve PHPMailer's recipient semantics. getAllRecipientAddresses() loses
    // the distinction between TO, CC and BCC, which can expose BCC recipients.
    $torecipients  = local_msgraph_api_mailer_normalise_addresses($mail->getToAddresses());
    $ccrecipients  = local_msgraph_api_mailer_normalise_addresses($mail->getCcAddresses());
    $bccrecipients = local_msgraph_api_mailer_normalise_addresses($mail->getBccAddresses());
    $replyto       = local_msgraph_api_mailer_normalise_addresses($mail->getReplyToAddresses());
    $subject       = $mail->Subject;

    // Prefer HTML body; fall back to plain-text body converted to safe HTML.
    $body = !empty($mail->Body) ? $mail->Body : nl2br(htmlspecialchars($mail->AltBody ?? ''));

    if (empty($torecipients) && empty($ccrecipients) && empty($bccrecipients)) {
        return; // Nothing to send — let PHPMailer proceed.
    }
    if ($subject === '') {
        return;
    }

    // Apply Moodle's email diverting setting while preserving TO/CC/BCC categories.
    global $CFG;
    if (!empty($CFG->divertallemailsto)) {
        $divertto   = trim($CFG->divertallemailsto);
        $exceptions = [];
        if (!empty($CFG->divertallemailsexcept)) {
            $exceptions = preg_split('/[\s,]+/', $CFG->divertallemailsexcept, -1, PREG_SPLIT_NO_EMPTY);
        }
        $torecipients  = local_msgraph_api_mailer_divert_addresses($torecipients, $divertto, $exceptions);
        $ccrecipients  = local_msgraph_api_mailer_divert_addresses($ccrecipients, $divertto, $exceptions);
        $bccrecipients = local_msgraph_api_mailer_divert_addresses($bccrecipients, $divertto, $exceptions);
    }

    // Flatten recipient addresses only for the existing log table.
    $recipients = local_msgraph_api_mailer_flatten_addresses([
        $torecipients,
        $ccrecipients,
        $bccrecipients,
    ]);

    require_once(__DIR__ . '/classes/api/graph_client.php');

    // Extract attachments from the PHPMailer object.
    // Each attachment array has 8 fields: path/content (0), name (1), basename (2),
    // encoding (3), mimetype (4), isString (5), disposition (6), cid (7).
    $attachments = [];
    foreach ($mail->getAttachments() as $attach) {
        if (($attach[6] ?? 'attachment') === 'inline') {
            continue; // Skip embedded images (e.g. logo CIDs).
        }
        $attachments[] = [
            'filepath' => $attach[0], // File path OR raw string content when isstring=true.
            'filename' => $attach[2] ?: basename($attach[0]), // Display name or fallback to basename.
            'mimetype' => $attach[4] ?: 'application/octet-stream',
            'isstring' => !empty($attach[5]),
        ];
    }

    $hasattachment = !empty($attachments) ? 1 : 0;

    try {
        $client = new \local_msgraph_api_mailer\api\graph_client();
        $result = $client->send_email(
            $torecipients,
            $subject,
            $body,
            null,
            $attachments,
            $ccrecipients,
            $bccrecipients,
            $replyto
        );

        if ($result['success']) {
            // Log successful send (always, when plugin is enabled).
            local_msgraph_api_mailer_log_record(
                $recipients,
                $subject,
                1,
                'Accepted by Microsoft Graph (HTTP 202)',
                $hasattachment
            );

            // Prevent PHPMailer from sending a duplicate via SMTP. The email was
            // already delivered via Graph, so route PHPMailer's transport to an
            // OS-appropriate no-op command (sendmail mode). The no-op accepts any
            // stdin and exits 0, so PHPMailer::postSend() returns true — meaning
            // email_to_user() reports success AND Moodle's built-in "Test outgoing
            // mail configuration" passes. Recipients are left intact so preSend()
            // still succeeds. This fixes the Contact Site Support form, which is
            // one of the few callers that surfaces a false return as an error.
            $mail->isSendmail();
            $mail->Sendmail = local_msgraph_api_mailer_noop_sendmail();
        } else {
            // Graph API returned non-202. SMTP fallback is disabled by default
            // in the production fork; only an explicit admin opt-in allows it.
            $failuremessage = 'Microsoft Graph send failed: HTTP ' . (int) $result['http_code'];
            if (!empty($result['error'])) {
                $failuremessage .= ' - transport error: ' . substr((string) $result['error'], 0, 200);
            }
            local_msgraph_api_mailer_log_record(
                $recipients,
                $subject,
                0,
                $failuremessage,
                $hasattachment
            );
            // If fallback is disabled, also clear recipients to prevent SMTP send.
            if (!get_config('local_msgraph_api_mailer', 'fallback_smtp')) {
                $mail->clearAllRecipients();
            }
        }
    } catch (Exception $e) {
        // Graph API threw an exception (e.g. token failure, network error).
        // SMTP fallback is disabled by default in the production fork.
        local_msgraph_api_mailer_log_record($recipients, $subject, 0, $e->getMessage(), $hasattachment);
        // If SMTP fallback is disabled, prevent PHPMailer from sending too.
        if (!get_config('local_msgraph_api_mailer', 'fallback_smtp')) {
            $mail->clearAllRecipients();
        }
        // If SMTP fallback is enabled, fall through — PHPMailer will attempt SMTP.
    }
}

/**
 * Convert a PHPMailer address list to the internal address/name shape.
 *
 * @param array $addresses PHPMailer addresses in [email, name] form.
 * @return array Normalised addresses.
 */
function local_msgraph_api_mailer_normalise_addresses(array $addresses): array {
    $result = [];
    foreach ($addresses as $address) {
        $email = trim((string) ($address[0] ?? ''));
        if ($email === '') {
            continue;
        }
        $item = ['address' => $email];
        $name = trim((string) ($address[1] ?? ''));
        if ($name !== '') {
            $item['name'] = $name;
        }
        $result[] = $item;
    }
    return $result;
}

/**
 * Apply Moodle email diversion to one recipient category.
 *
 * @param array $addresses Normalised addresses.
 * @param string $divertto Divert target address.
 * @param array $exceptions Exception patterns.
 * @return array Diverted addresses with duplicates removed.
 */
function local_msgraph_api_mailer_divert_addresses(array $addresses, string $divertto, array $exceptions): array {
    $result = [];
    $seen = [];
    foreach ($addresses as $address) {
        $email = $address['address'];
        $excepted = false;
        foreach ($exceptions as $pattern) {
            if (stripos($email, $pattern) !== false) {
                $excepted = true;
                break;
            }
        }
        $target = $excepted ? $address : ['address' => $divertto];
        $key = strtolower($target['address']);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $result[] = $target;
        }
    }
    return $result;
}

/**
 * Flatten recipient groups to email strings for the existing log table.
 *
 * @param array $groups Recipient groups.
 * @return array Unique email addresses.
 */
function local_msgraph_api_mailer_flatten_addresses(array $groups): array {
    $result = [];
    foreach ($groups as $group) {
        foreach ($group as $address) {
            if (!empty($address['address'])) {
                $result[strtolower($address['address'])] = $address['address'];
            }
        }
    }
    return array_values($result);
}

/**
 * Return an OS-appropriate no-op command for PHPMailer's sendmail mode.
 *
 * After an email is delivered via Graph, PHPMailer is pointed at this command to
 * suppress the duplicate SMTP send while still letting PHPMailer::postSend()
 * return true. The command must consume stdin and exit 0.
 *
 * @return string Path or name of a command that ignores its input and exits 0.
 */
function local_msgraph_api_mailer_noop_sendmail() {
    if (DIRECTORY_SEPARATOR === '\\') {
        // Windows: 'rem' is a cmd.exe built-in that ignores all arguments and exits 0.
        // PHP's popen() runs commands through cmd.exe, so this returns success.
        return 'rem';
    }
    foreach (['/bin/true', '/usr/bin/true'] as $candidate) {
        if (is_executable($candidate)) {
            return $candidate;
        }
    }
    // Last resort: resolve 'true' via PATH.
    return 'true';
}

/**
 * Write an email send record to the log table.
 * Only writes when the 'log_emails' setting is enabled (default: yes).
 *
 * @param array  $recipients     Array of recipient email addresses.
 * @param string $subject        Email subject.
 * @param int    $status         1 = sent, 0 = failed.
 * @param string $response       API response or error message.
 * @param int    $hasattachment  1 if the email had attachments, 0 otherwise.
 */
function local_msgraph_api_mailer_log_record($recipients, $subject, $status, $response, $hasattachment = 0) {
    // Only skip when explicitly set to '0'. Treat missing (false) as enabled — the default.
    if (get_config('local_msgraph_api_mailer', 'log_emails') === '0') {
        return;
    }
    global $DB;
    try {
        $record                 = new stdClass();
        $record->recipients     = json_encode($recipients);
        $record->subject        = $subject;
        $record->status         = (int) $status;
        $record->has_attachment = (int) $hasattachment;
        $record->response       = substr((string) $response, 0, 2000);
        $record->timecreated    = time();
        $DB->insert_record('local_msgraph_api_mailer_log', $record, false);
    } catch (Exception $e) {
        // Ignore logging errors (e.g. table not yet installed on first run).
        unset($e);
    }
}

/**
 * Cron task placeholder for future queue processing.
 *
 * @return bool True always.
 */
function local_msgraph_api_mailer_cron() {
    return true;
}

// Moodle PHPMailer patch helpers.

/** @var string Relative path (from dirroot) to the file we patch. */
define('LOCAL_MSGRAPH_API_MAILER_PHPMAILER_REL', '/lib/phpmailer/moodle_phpmailer.php');

/** @var string Unique string present in our injected block — used to detect if already patched. */
define('LOCAL_MSGRAPH_API_MAILER_PATCH_MARKER', "get_plugins_with_function('phpmailer_init')");

/**
 * The exact string in postSend()'s else-branch that we use as the injection point.
 */
define('LOCAL_MSGRAPH_API_MAILER_PATCH_ANCHOR', "        } else {\n            return parent::postSend();");

/** @var string Our hook block — replaces the anchor above. */
define(
    'LOCAL_MSGRAPH_API_MAILER_PATCH_REPLACEMENT',
    "        } else {\n" .
    "            // Call phpmailer_init hooks so local plugins can intercept outgoing email\n" .
    "            // (restored by local_msgraph_api_mailer — remove this plugin to undo).\n" .
    "            \$pluginswithfunction = get_plugins_with_function('phpmailer_init');\n" .
    "            foreach (\$pluginswithfunction as \$plugins) {\n" .
    "                foreach (\$plugins as \$function) {\n" .
    "                    \$function(\$this);\n" .
    "                }\n" .
    "            }\n" .
    "            return parent::postSend();"
);

/**
 * Write $newcontent to $filepath and invalidate OPcache.
 *
 * @param string $filepath   Absolute path to the file to write.
 * @param string $newcontent New file content to write.
 */
function local_msgraph_api_mailer_write_phpmailer(string $filepath, string $newcontent): void {
    file_put_contents($filepath, $newcontent);
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($filepath, true);
    }
}

/**
 * Inject the phpmailer_init hook into moodle_phpmailer::postSend().
 * Safe to call multiple times — skips if already patched.
 *
 * @return string 'ok'               Patch applied successfully.
 *                'already_patched'  Patch marker already present; no action needed.
 *                'not_readable'     File cannot be read.
 *                'not_writable'     File is read-only (immutable deployment, etc.).
 *                'anchor_not_found' Moodle changed postSend() — injection point missing.
 */
function local_msgraph_api_mailer_apply_phpmailer_patch(): string {
    global $CFG;
    $filepath = $CFG->dirroot . LOCAL_MSGRAPH_API_MAILER_PHPMAILER_REL;

    if (!is_readable($filepath)) {
        return 'not_readable';
    }

    $content = file_get_contents($filepath);

    if (strpos($content, LOCAL_MSGRAPH_API_MAILER_PATCH_MARKER) !== false) {
        return 'already_patched';
    }

    if (!is_writable($filepath)) {
        return 'not_writable';
    }

    $patched = str_replace(
        LOCAL_MSGRAPH_API_MAILER_PATCH_ANCHOR,
        LOCAL_MSGRAPH_API_MAILER_PATCH_REPLACEMENT,
        $content
    );

    if ($patched === $content) {
        return 'anchor_not_found';
    }

    local_msgraph_api_mailer_write_phpmailer($filepath, $patched);
    return 'ok';
}

/**
 * Remove the phpmailer_init hook from moodle_phpmailer::postSend(),
 * restoring the original Moodle file. Called on plugin uninstall.
 *
 * @return bool True on success or if patch was not present.
 */
function local_msgraph_api_mailer_remove_phpmailer_patch() {
    global $CFG;
    $filepath = $CFG->dirroot . LOCAL_MSGRAPH_API_MAILER_PHPMAILER_REL;

    if (!is_readable($filepath) || !is_writable($filepath)) {
        return false;
    }

    $content  = file_get_contents($filepath);
    $ok       = true;

    if (strpos($content, LOCAL_MSGRAPH_API_MAILER_PATCH_MARKER) !== false) {
        $restored = str_replace(
            LOCAL_MSGRAPH_API_MAILER_PATCH_REPLACEMENT,
            LOCAL_MSGRAPH_API_MAILER_PATCH_ANCHOR,
            $content
        );
        $ok = ($restored !== $content);
        if ($ok) {
            local_msgraph_api_mailer_write_phpmailer($filepath, $restored);
        }
    }

    return $ok;
}

// The after_config logic is registered via db/hooks.php using the Moodle 5.x
// hook system. See classes/hook/after_config_callbacks.php.
