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
 * Microsoft Graph API client for sending emails.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_msgraph_api_mailer\api;

/**
 * Microsoft Graph API client for sending emails.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class graph_client {
    /**
     * Maximum attachment size supported by this production fork.
     *
     * Large attachment upload sessions require Mail.ReadWrite because Graph must
     * create and modify a draft message. This fork deliberately stays on the
     * least-privilege Mail.Send permission, so attachments above this limit are
     * rejected instead of using the draft/upload-session flow.
     */
    private const MAX_INLINE_ATTACHMENT_BYTES = 2097152; // 2 MiB.

    /** @var string Azure AD tenant ID. */
    private $tenantid;
    /** @var string Azure AD client ID. */
    private $clientid;
    /** @var string Azure AD client secret. */
    private $clientsecret;
    /** @var string|null Process-local cached OAuth2 access token. */
    private static $accesstoken = null;
    /** @var int Unix timestamp when the process-local cached token expires. */
    private static $tokenexpiry = 0;
    /** @var string Cache key binding the token to tenant + client ID. */
    private static $tokencachekey = '';

    /**
     * Constructor — reads plugin configuration from Moodle settings.
     */
    public function __construct() {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $this->tenantid = trim((string) get_config('local_msgraph_api_mailer', 'tenant_id'));
        $this->clientid = trim((string) get_config('local_msgraph_api_mailer', 'client_id'));
        $this->clientsecret = trim(
            !empty($CFG->local_msgraph_api_mailer_client_secret)
                ? (string) $CFG->local_msgraph_api_mailer_client_secret
                : (string) get_config('local_msgraph_api_mailer', 'client_secret')
        );
    }

    /**
     * Obtain (or return cached) OAuth2 access token from Azure AD.
     *
     * @return string Valid access token string.
     * @throws \Exception When configuration is missing or token request fails.
     */
    public function get_access_token() {
        $cachekey = $this->tenantid . ':' . $this->clientid;
        if (
            self::$accesstoken &&
            self::$tokencachekey === $cachekey &&
            time() < self::$tokenexpiry
        ) {
            return self::$accesstoken;
        }

        if (empty($this->tenantid) || empty($this->clientid) || empty($this->clientsecret)) {
            throw new \Exception('MS Graph Mailer: Missing configuration (Tenant ID, Client ID, or Client Secret)');
        }

        $url = 'https://login.microsoftonline.com/' . rawurlencode($this->tenantid) . '/oauth2/v2.0/token';
        $postdata = 'grant_type=client_credentials'
            . '&client_id='     . rawurlencode($this->clientid)
            . '&client_secret=' . rawurlencode($this->clientsecret)
            . '&scope='         . rawurlencode('https://graph.microsoft.com/.default');

        $curl     = new \curl(['proxy' => true]);
        $response = $curl->post($url, $postdata, [
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: ' . strlen($postdata),
            ],
            'CURLOPT_TIMEOUT'    => 30,
        ]);
        $httpcode = (int) $curl->get_info()['http_code'];
        $error    = $curl->error;

        if ($httpcode !== 200) {
            $message = 'MS Graph Mailer: Access token request failed (HTTP ' . $httpcode . ')';
            if (!empty($error)) {
                $message .= ' - transport error: ' . substr((string) $error, 0, 200);
            }
            throw new \Exception($message);
        }

        $json = json_decode($response, true);
        if (!isset($json['access_token'])) {
            throw new \Exception('MS Graph Mailer: Access token response was invalid or incomplete');
        }

        self::$accesstoken = $json['access_token'];
        self::$tokenexpiry = time() + max(60, (($json['expires_in'] ?? 3600) - 300));
        self::$tokencachekey = $cachekey;

        return self::$accesstoken;
    }

    /**
     * Clear the process-local access token cache.
     *
     * Used after an HTTP 401 so one fresh token can be requested.
     */
    private function invalidate_access_token(): void {
        self::$accesstoken = null;
        self::$tokenexpiry = 0;
        self::$tokencachekey = '';
    }

    /**
     * Send an email via Microsoft Graph API using the least-privilege sendMail path.
     *
     * @param string|array $to          TO recipients.
     * @param string       $subject     Email subject.
     * @param string       $body        HTML email body.
     * @param string|null  $from        Unused — sender is always read from plugin config.
     * @param array        $attachments Optional list of attachment arrays.
     * @param array        $cc          CC recipients.
     * @param array        $bcc         BCC recipients.
     * @param array        $replyto     Reply-To recipients.
     * @return array Result with 'success', 'http_code', 'response', 'error' keys.
     */
    public function send_email(
        $to,
        $subject,
        $body,
        $from = null,
        $attachments = [],
        $cc = [],
        $bcc = [],
        $replyto = []
    ) {
        $token              = $this->get_access_token();
        $senderemail       = trim((string) get_config('local_msgraph_api_mailer', 'sender_email'));
        $senderdisplayname = trim((string) get_config('local_msgraph_api_mailer', 'sender_display_name'));

        if (empty($senderemail)) {
            throw new \Exception('MS Graph Mailer: Sender email not configured');
        }

        $fromaddress = ['address' => $senderemail];
        if (!empty($senderdisplayname)) {
            $fromaddress['name'] = $senderdisplayname;
        }

        // Process all attachments using the inline Graph fileAttachment path only.
        // Large attachment upload sessions are intentionally disabled because they
        // require Mail.ReadWrite; this fork is designed to use Mail.Send only.
        [$smallattachments, $largeattachments] = $this->split_attachments(
            $attachments,
            self::MAX_INLINE_ATTACHMENT_BYTES
        );

        if (!empty($largeattachments)) {
            $largest = 0;
            foreach ($largeattachments as $attachment) {
                $largest = max($largest, (int) ($attachment['size'] ?? 0));
            }
            throw new \Exception(
                'MS Graph Mailer: Attachment exceeds the 2 MiB production limit. ' .
                'Large attachments are disabled to preserve least-privilege Mail.Send access. ' .
                'Largest attachment: ' . $largest . ' bytes.'
            );
        }

        $message = [
            'subject'      => $subject,
            'body'         => ['contentType' => 'HTML', 'content' => $body],
            'from'         => ['emailAddress' => $fromaddress],
            'toRecipients' => $this->format_recipients($to),
        ];

        if (!empty($cc)) {
            $message['ccRecipients'] = $this->format_recipients($cc);
        }
        if (!empty($bcc)) {
            $message['bccRecipients'] = $this->format_recipients($bcc);
        }
        if (!empty($replyto)) {
            $message['replyTo'] = $this->format_recipients($replyto);
        }

        if (!empty($smallattachments)) {
            $message['attachments'] = $smallattachments;
        }

        // Single POST to /sendMail. No draft is created, so Mail.ReadWrite is not required.
        $postdata = json_encode(['message' => $message]);
        $url      = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($senderemail) . '/sendMail';
        $result   = $this->graph_request($url, $token, 'POST', $postdata);

        return [
            'success'   => ($result['http_code'] === 202),
            'http_code' => $result['http_code'],
            'response'  => $result['body'],
            'error'     => $result['error'],
        ];
    }

    /**
     * Test the Graph API connection by requesting an access token.
     *
     * @return array Result with 'success' and 'message' keys.
     */
    public function test_connection() {
        try {
            $this->get_access_token();
            return ['success' => true, 'message' => 'Connection successful'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Private helpers.

    /**
     * Process raw attachment list: load file content, detect MIME, fix filename,
     * and separate supported inline attachments from oversized attachments.
     *
     * @param array $attachments   Raw attachment list from PHPMailer.
     * @param int   $thresholdbytes Byte size at/above which the attachment is rejected.
     * @return array [smallattachments[], largeattachments[]]
     */
    private function split_attachments($attachments, $thresholdbytes) {
        $small = [];
        $large = [];

        foreach ($attachments as $attachment) {
            if (!empty($attachment['isstring'])) {
                // AddStringAttachment(): filepath holds raw string content, not a path.
                $content  = $attachment['filepath'];
                $mimetype = $attachment['mimetype'] ?? 'application/octet-stream';
            } else if (file_exists($attachment['filepath'])) {
                $content  = file_get_contents($attachment['filepath']);
                $mimetype = $attachment['mimetype'] ?? 'application/octet-stream';
            } else {
                continue; // File missing — skip.
            }

            if ($content === false || $content === '') {
                continue;
            }

            // Detect real MIME from in-memory content (avoids reopening temp files
            // that Moodle may delete before a second fopen call).
            if ($mimetype === 'application/octet-stream' || $mimetype === 'application/zip') {
                $detected = $this->detect_mime_from_content($content);
                if ($detected !== null) {
                    $mimetype = $detected;
                }
            }

            // Ensure filename has a proper extension.
            $name = $attachment['filename'];
            if (pathinfo($name, PATHINFO_EXTENSION) === '') {
                $ext = $this->mime_to_extension($mimetype);
                if ($ext !== '') {
                    $name .= '.' . $ext;
                }
            }

            $size = strlen($content);

            if ($size >= $thresholdbytes) {
                // Oversized: caller rejects these to preserve Mail.Send-only permissions.
                $large[] = [
                    'name'     => $name,
                    'mimetype' => $mimetype,
                    'content'  => $content,
                    'size'     => $size,
                ];
            } else {
                // Small: include inline as base64 in the message body.
                $graphattachment = [
                    '@odata.type'  => '#microsoft.graph.fileAttachment',
                    'name'         => $name,
                    'contentType'  => $mimetype,
                    'contentBytes' => base64_encode($content),
                ];

                if (!empty($attachment['isinline'])) {
                    $graphattachment['isInline'] = true;
                    if (!empty($attachment['cid'])) {
                        $graphattachment['contentId'] = $attachment['cid'];
                    }
                }

                $small[] = $graphattachment;
            }
        }

        return [$small, $large];
    }

    /**
     * Generic Graph API HTTP request helper.
     *
     * @param string $url    Full Graph API endpoint URL.
     * @param string $token  OAuth2 bearer token.
     * @param string $method HTTP method (GET, POST, DELETE, etc.).
     * @param string $body   Request body; empty string for requests with no body.
     * @return array Array with 'body', 'http_code', and 'error' keys.
     */
    private function graph_request($url, $token, $method, $body) {
        $attempt = 0;
        $refreshedtoken = false;
        $lastresult = ['body' => '', 'http_code' => 0, 'error' => ''];

        while ($attempt < 3) {
            $attempt++;
            $responseheaders = [];

            $headers = [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ];
            if ($body !== '') {
                $headers[] = 'Content-Length: ' . strlen($body);
            }

            $curl = new \curl(['proxy' => true]);
            $options = [
                'CURLOPT_CUSTOMREQUEST' => $method,
                'CURLOPT_HTTPHEADER' => $headers,
                'CURLOPT_CONNECTTIMEOUT' => 5,
                'CURLOPT_TIMEOUT' => 20,
                'CURLOPT_HEADERFUNCTION' => static function($handle, $headerline) use (&$responseheaders) {
                    $length = strlen($headerline);
                    $parts = explode(':', $headerline, 2);
                    if (count($parts) === 2) {
                        $responseheaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                    }
                    return $length;
                },
            ];

            // post() is the Moodle curl method that accepts a raw string body;
            // CURLOPT_CUSTOMREQUEST selects the required HTTP verb.
            $resp = $curl->post($url, $body, $options);
            $httpcode = (int) ($curl->get_info()['http_code'] ?? 0);
            $curlerr = (string) $curl->error;
            $lastresult = [
                'body' => $resp,
                'http_code' => $httpcode,
                'error' => $curlerr,
            ];

            // A stale/invalid token gets exactly one refresh attempt.
            if ($httpcode === 401 && !$refreshedtoken) {
                $this->invalidate_access_token();
                $token = $this->get_access_token();
                $refreshedtoken = true;
                continue;
            }

            // Retry only explicit transient HTTP responses. Network timeouts are
            // not retried automatically because sendMail may already have been accepted.
            if (in_array($httpcode, [429, 502, 503, 504], true) && $attempt < 3) {
                $delay = $attempt === 1 ? 2 : 5;
                if ($httpcode === 429 && !empty($responseheaders['retry-after'])) {
                    $retryafter = (int) $responseheaders['retry-after'];
                    if ($retryafter > 0) {
                        $delay = min($retryafter, 30);
                    }
                }
                sleep($delay);
                continue;
            }

            break;
        }

        return $lastresult;
    }

    /**
     * Format one or more recipient email addresses into Graph API recipient objects.
     *
     * @param string|array $recipients Single email address string or array of strings.
     * @return array Array of Graph API recipient objects.
     */
    private function format_recipients($recipients) {
        $formatted = [];
        if (!is_array($recipients)) {
            $recipients = [$recipients];
        }

        foreach ($recipients as $recipient) {
            if (is_array($recipient)) {
                $email = trim((string) ($recipient['address'] ?? ''));
                $name  = trim((string) ($recipient['name'] ?? ''));
            } else {
                $email = trim((string) $recipient);
                $name  = '';
            }

            if ($email === '') {
                continue;
            }

            $emailaddress = ['address' => $email];
            if ($name !== '') {
                $emailaddress['name'] = $name;
            }
            $formatted[] = ['emailAddress' => $emailaddress];
        }

        return $formatted;
    }

    /**
     * Detect MIME type from file content already loaded in memory.
     * Reads magic bytes and, for ZIP files, searches for OOXML marker paths.
     * Does NOT reopen the file — temp files may already be deleted by Moodle.
     *
     * @param string $content Raw file content already loaded into memory.
     * @return string|null Detected MIME type string, or null if unrecognised.
     */
    private function detect_mime_from_content($content) {
        if (strlen($content) < 4) {
            return null;
        }
        $magic = substr($content, 0, 8);

        // PDF.
        if (substr($magic, 0, 4) === '%PDF') {
            return 'application/pdf';
        }

        // OLE2 Compound Document — legacy .xls / .doc / .ppt.
        if ($magic === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            return 'application/vnd.ms-excel';
        }

        // ZIP-based (OOXML: xlsx, docx, pptx).
        if (substr($magic, 0, 4) === "PK\x03\x04") {
            // OOXML files contain their part paths in the ZIP central directory.
            if (strpos($content, 'xl/workbook') !== false || strpos($content, 'xl/_rels') !== false) {
                return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            }
            if (strpos($content, 'word/document') !== false || strpos($content, 'word/_rels') !== false) {
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            }
            if (strpos($content, 'ppt/presentation') !== false || strpos($content, 'ppt/_rels') !== false) {
                return 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
            }
            return 'application/zip';
        }

        return null;
    }

    /**
     * Map a MIME type string to a file extension (without the dot).
     *
     * @param string $mimetype MIME type string (e.g. 'application/pdf').
     * @return string File extension without dot, or empty string if unknown.
     */
    private function mime_to_extension($mimetype) {
        $map = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/vnd.ms-excel'                                                   => 'xls',
            'application/vnd.ms-word'                                                    => 'doc',
            'application/vnd.ms-powerpoint'                                              => 'ppt',
            'application/pdf'                                                            => 'pdf',
            'application/zip'                                                            => 'zip',
            'text/csv'                                                                   => 'csv',
            'text/plain'                                                                 => 'txt',
            'image/jpeg'                                                                 => 'jpg',
            'image/png'                                                                  => 'png',
            'image/gif'                                                                  => 'gif',
        ];
        return $map[$mimetype] ?? '';
    }
}
