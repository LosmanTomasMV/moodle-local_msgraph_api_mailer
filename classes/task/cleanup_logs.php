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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.

/**
 * Scheduled task for pruning old mail log records.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_msgraph_api_mailer\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Delete mail log records older than the configured retention period.
 */
class cleanup_logs extends \core\task\scheduled_task {
    /**
     * Return the task name shown in Scheduled tasks.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup_logs', 'local_msgraph_api_mailer');
    }

    /**
     * Execute log cleanup.
     */
    public function execute(): void {
        global $DB;

        $retention = get_config('local_msgraph_api_mailer', 'log_retention');
        if ($retention === false || $retention === null || $retention === '') {
            $retention = 90 * DAYSECS;
        }

        // Never accept a zero/negative retention by mistake.
        $retention = max(DAYSECS, (int) $retention);
        $cutoff = time() - $retention;

        $count = $DB->count_records_select(
            'local_msgraph_api_mailer_log',
            'timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );

        if ($count > 0) {
            $DB->delete_records_select(
                'local_msgraph_api_mailer_log',
                'timecreated < :cutoff',
                ['cutoff' => $cutoff]
            );
        }

        mtrace('MS Graph API Mailer: removed ' . $count . ' expired mail log record(s).');
    }
}
