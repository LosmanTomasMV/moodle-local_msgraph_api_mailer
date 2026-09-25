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
 * CLI tool for controlled management of the Moodle PHPMailer core patch.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once(__DIR__ . '/../lib.php');

[$options, $unrecognized] = cli_get_params([
    'help' => false,
    'status' => false,
    'apply' => false,
    'remove' => false,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    cli_error('Unknown option(s): ' . implode(', ', $unrecognized));
}

$actions = array_filter([
    'status' => !empty($options['status']),
    'apply' => !empty($options['apply']),
    'remove' => !empty($options['remove']),
]);

if ($options['help'] || count($actions) !== 1) {
    cli_writeln('MS Graph Mailer - Core Patch Tool');
    cli_writeln('=================================');
    cli_writeln('Usage:');
    cli_writeln('  php patch.php --status');
    cli_writeln('  php patch.php --apply');
    cli_writeln('  php patch.php --remove');
    cli_writeln('');
    cli_writeln('The production fork never applies the Moodle core patch automatically.');
    cli_writeln('Review Moodle compatibility before using --apply after an upgrade.');
    exit($options['help'] ? 0 : 1);
}

$filepath = $CFG->dirroot . LOCAL_MSGRAPH_API_MAILER_PHPMAILER_REL;

/**
 * Determine current patch state without changing the core file.
 *
 * @param string $filepath Absolute path to moodle_phpmailer.php.
 * @return string
 */
function local_msgraph_api_mailer_cli_patch_status(string $filepath): string {
    if (!is_readable($filepath)) {
        return 'not_readable';
    }

    $content = file_get_contents($filepath);
    if ($content === false) {
        return 'not_readable';
    }

    $hasbegin = strpos($content, LOCAL_MSGRAPH_API_MAILER_PATCH_BEGIN) !== false;
    $hasend = strpos($content, LOCAL_MSGRAPH_API_MAILER_PATCH_END) !== false;

    if ($hasbegin && $hasend) {
        return 'applied';
    }
    if ($hasbegin || $hasend) {
        return 'incomplete';
    }
    return 'missing';
}

if ($options['status']) {
    $status = local_msgraph_api_mailer_cli_patch_status($filepath);
    cli_writeln('File: ' . $filepath);
    cli_writeln('Patch status: ' . $status);
    cli_writeln('Writable: ' . (is_writable($filepath) ? 'yes' : 'no'));

    if ($status === 'applied') {
        set_config('patch_status', 'ok', 'local_msgraph_api_mailer');
        exit(0);
    }

    set_config(
        'patch_status',
        $status === 'not_readable' ? 'not_readable' : 'manual_required',
        'local_msgraph_api_mailer'
    );
    exit($status === 'missing' ? 2 : 1);
}

if ($options['apply']) {
    $before = local_msgraph_api_mailer_cli_patch_status($filepath);

    if ($before === 'incomplete') {
        cli_error(
            'Refusing to patch: only one production patch marker is present. ' .
            'Inspect moodle_phpmailer.php manually before continuing.'
        );
    }

    try {
        $result = local_msgraph_api_mailer_apply_phpmailer_patch();
    } catch (Throwable $e) {
        set_config('patch_status', 'failed_unknown', 'local_msgraph_api_mailer');
        cli_error('Patch failed: ' . $e->getMessage());
    }

    if (in_array($result, ['ok', 'already_patched'], true)) {
        set_config('patch_status', 'ok', 'local_msgraph_api_mailer');
        cli_writeln($result === 'ok' ? 'Patch applied successfully.' : 'Patch is already applied.');
        cli_writeln('Run: php patch.php --status');
        exit(0);
    }

    $statusmap = [
        'not_readable' => 'not_readable',
        'not_writable' => 'failed_readonly',
        'anchor_not_found' => 'failed_anchor',
        'unsupported_core' => 'unsupported_core',
    ];
    set_config('patch_status', $statusmap[$result] ?? 'failed_unknown', 'local_msgraph_api_mailer');
    cli_error('Patch was not applied. Result: ' . $result);
}

if ($options['remove']) {
    $before = local_msgraph_api_mailer_cli_patch_status($filepath);

    if ($before === 'incomplete') {
        cli_error(
            'Refusing to remove patch: only one production patch marker is present. ' .
            'Inspect moodle_phpmailer.php manually.'
        );
    }

    if ($before === 'missing') {
        set_config('patch_status', 'manual_required', 'local_msgraph_api_mailer');
        cli_writeln('Patch is already absent.');
        exit(0);
    }

    try {
        $removed = local_msgraph_api_mailer_remove_phpmailer_patch();
    } catch (Throwable $e) {
        cli_error('Patch removal failed: ' . $e->getMessage());
    }

    if (!$removed) {
        cli_error('Patch removal failed. The core file was not changed.');
    }

    set_config('patch_status', 'manual_required', 'local_msgraph_api_mailer');
    cli_writeln('Patch removed successfully.');
    exit(0);
}
