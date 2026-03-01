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
 * CLI script to clean up old MoodleTestGeneratorPlugin data.
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
        'days' => 30,
        'dry-run' => false,
        'force' => false,
    ],
    [
        'h' => 'help',
        'd' => 'days',
        'n' => 'dry-run',
        'f' => 'force',
    ]
);

if ($options['help']) {
    echo "Clean up old MoodleTestGeneratorPlugin data.

Options:
    -h, --help          Show this help
    -d, --days=N        Delete data older than N days (default: 30)
    -n, --dry-run       Show what would be deleted without deleting
    -f, --force         Skip confirmation prompt

Examples:
    php cleanup.php              # Show what would be deleted (30 days)
    php cleanup.php -n           # Dry run
    php cleanup.php -d 7 -f      # Delete data older than 7 days
";
    exit(0);
}

echo "MoodleTestGeneratorPlugin - Cleanup Tool\n";
echo "==================================\n\n";

$days = $options['days'];
$dryrun = $options['dry-run'];
$force = $options['force'];

global $DB;

$cutoff = time() - ($days * DAYSECS);

echo "Cutoff date: " . userdate($cutoff) . "\n";
echo "Mode: " . ($dryrun ? "DRY RUN" : "LIVE") . "\n\n";

// Count old logs
$logcount = $DB->count_records_select('local_pdfquizgen_logs', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
echo "Old logs to delete: $logcount\n";

// Count old completed/failed jobs
$jobcount = $DB->count_records_select(
    'local_pdfquizgen_jobs',
    'timecreated < :cutoff AND status IN ("completed", "failed")',
    ['cutoff' => $cutoff]
);
echo "Old jobs to delete: $jobcount\n";

// Count orphaned questions
$orphanedquestions = $DB->count_records_sql(
    "SELECT COUNT(*)
       FROM {local_pdfquizgen_questions} q
       LEFT JOIN {local_pdfquizgen_jobs} j ON q.jobid = j.id
      WHERE j.id IS NULL"
);
echo "Orphaned questions to delete: $orphanedquestions\n";

$total = $logcount + $jobcount + $orphanedquestions;

echo "\nTotal records: $total\n\n";

if ($total == 0) {
    echo "Nothing to clean up.\n";
    exit(0);
}

if (!$force && !$dryrun) {
    echo "Are you sure you want to delete these records? [y/N]: ";
    $input = fgets(STDIN);
    $confirm = trim((string)($input ?? ''));
    if (strtolower($confirm) !== 'y') {
        echo "Cancelled.\n";
        exit(0);
    }
}

if ($dryrun) {
    echo "*** DRY RUN - No records were deleted ***\n";
    exit(0);
}

// Delete old logs
echo "Deleting old logs...\n";
$DB->delete_records_select('local_pdfquizgen_logs', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
echo "  ✓ Done\n";

// Delete orphaned questions first (to avoid foreign key issues)
if ($orphanedquestions > 0) {
    echo "Deleting orphaned questions...\n";
    $DB->execute(
        "DELETE FROM {local_pdfquizgen_questions}
          WHERE jobid NOT IN (SELECT id FROM {local_pdfquizgen_jobs})"
    );
    echo "  ✓ Done\n";
}

// Delete old jobs (this will cascade to questions if foreign keys are set up)
echo "Deleting old jobs...\n";
$oldjobs = $DB->get_records_select(
    'local_pdfquizgen_jobs',
    'timecreated < :cutoff AND status IN ("completed", "failed")',
    ['cutoff' => $cutoff],
    '',
    'id, quizid, courseid'
);

$deletedjobs = 0;
foreach ($oldjobs as $job) {
    // Delete associated quiz if exists
    if ($job->quizid) {
        $cm = get_coursemodule_from_instance('quiz', $job->quizid);
        if ($cm) {
            course_delete_module($cm->id);
        }
    }

    // Delete questions
    $DB->delete_records('local_pdfquizgen_questions', ['jobid' => $job->id]);

    // Delete job
    $DB->delete_records('local_pdfquizgen_jobs', ['id' => $job->id]);
    $deletedjobs++;
}
echo "  ✓ Deleted $deletedjobs jobs\n";

echo "\nCleanup complete!\n";
exit(0);
