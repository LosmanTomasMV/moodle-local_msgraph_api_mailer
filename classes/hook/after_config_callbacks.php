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
 * Hook callbacks for local_msgraph_api_mailer.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_msgraph_api_mailer\hook;

/**
 * Hook callbacks for local_msgraph_api_mailer.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_config_callbacks {
    /**
     * Check patch status without modifying Moodle core.
     *
     * A missing patch after an upgrade is reported as manual_required. The
     * production fork never silently re-applies changes to Moodle core.
     *
     * @param \core\hook\after_config $hook The after_config hook instance.
     */
    public static function check_phpmailer_patch(\core\hook\after_config $hook): void {
        global $CFG;

        $filepath = $CFG->dirroot . '/lib/phpmailer/moodle_phpmailer.php';

        if (!is_readable($filepath)) {
            $newstatus = 'not_readable';
        } else {
            $content = file_get_contents($filepath);
            $hasbegin = strpos($content, '// LOCAL_MSGRAPH_API_MAILER_PATCH_BEGIN') !== false;
            $hasend = strpos($content, '// LOCAL_MSGRAPH_API_MAILER_PATCH_END') !== false;
            $newstatus = ($hasbegin && $hasend) ? 'ok' : 'manual_required';
        }

        // Only write to DB when the status changes to avoid unnecessary writes on every request.
        if ($newstatus !== get_config('local_msgraph_api_mailer', 'patch_status')) {
            set_config('patch_status', $newstatus, 'local_msgraph_api_mailer');
        }
    }
}
