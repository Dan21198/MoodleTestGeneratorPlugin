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
 * CLI script to process pending PDF Quiz Generator jobs.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Get CLI options
list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'jobid' => 0,
        'limit' => 10,
        'dry-run' => false,
    ],
    [
        'h' => 'help',
        'j' => 'jobid',
        'l' => 'limit',
        'd' => 'dry-run',
    ]
);

if ($options['help']) {
    echo "Process pending PDF Quiz Generator jobs.

Options:
    -h, --help          Show this help
    -j, --jobid=N       Process specific job ID
    -l, --limit=N       Maximum jobs to process (default: 10)
    -d, --dry-run       Show what would be done without processing

Examples:
    php process_jobs.php              # Process up to 10 pending jobs
    php process_jobs.php -l 5         # Process up to 5 pending jobs
    php process_jobs.php -j 123       # Process specific job
    php process_jobs.php -d           # Dry run
";
    exit(0);
}

echo "PDF Quiz Generator - Job Processor\n";
echo "===================================\n\n";

$dryrun = $options['dry-run'];
$jobid = $options['jobid'];
$limit = $options['limit'];

if ($dryrun) {
    echo "*** DRY RUN MODE - No changes will be made ***\n\n";
}

// Get jobs to process
global $DB;

if ($jobid) {
    $jobs = $DB->get_records('local_pdfquizgen_jobs', ['id' => $jobid]);
    if (empty($jobs)) {
        cli_error("Job ID $jobid not found!");
    }
} else {
    $jobs = $DB->get_records(
        'local_pdfquizgen_jobs',
        ['status' => 'pending'],
        'timecreated ASC',
        '*',
        0,
        $limit
    );
}

if (empty($jobs)) {
    echo "No pending jobs found.\n";
    exit(0);
}

echo "Found " . count($jobs) . " job(s) to process.\n\n";

$success = 0;
$failed = 0;

foreach ($jobs as $job) {
    echo "Processing job #{$job->id}...\n";
    echo "  Course: {$job->courseid}\n";
    echo "  File: {$job->filename}\n";
    echo "  Questions: {$job->questioncount} ({$job->questiontype})\n";

    if ($dryrun) {
        echo "  [DRY RUN - Skipped]\n\n";
        continue;
    }

    $jobmanager = new \local_pdfquizgen\job_manager($job->courseid, $job->userid);
    $result = $jobmanager->process_job($job->id);

    if ($result['success']) {
        echo "  ✓ Success! Quiz ID: {$result['quizid']}\n";
        $success++;
    } else {
        echo "  ✗ Failed: {$result['error']}\n";
        $failed++;
    }
    echo "\n";
}

echo "===================================\n";
echo "Processing complete!\n";
echo "Successful: $success\n";
echo "Failed: $failed\n";

exit($failed > 0 ? 1 : 0);
