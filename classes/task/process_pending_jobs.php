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
 * Scheduled task to process PDF Quiz Generator jobs.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Task to process jobs in processing state.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_pending_jobs extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_process_jobs', 'local_pdfquizgen');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        mtrace('PDF Quiz Generator: Processing jobs...');

        // Get jobs that are in processing state
        $jobs = $DB->get_records(
            'local_pdfquizgen_jobs',
            ['status' => 'processing'],
            'timecreated ASC',
            '*',
            0,
            5 // Process max 5 jobs per run
        );

        if (empty($jobs)) {
            mtrace('No jobs to process.');
            return;
        }

        mtrace('Found ' . count($jobs) . ' job(s) to process.');

        $success = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            mtrace("Processing job #{$job->id}...");

            // Get admin user for processing
            $admin = get_admin();

            $jobmanager = new \local_pdfquizgen\job_manager($job->courseid, $admin->id);
            $result = $jobmanager->process_job($job->id);

            if ($result['success']) {
                mtrace("  Success! Quiz ID: {$result['quizid']}");
                $success++;
            } else {
                mtrace("  Failed: {$result['error']}");
                $failed++;
            }
        }

        mtrace("Processing complete. Successful: $success, Failed: $failed");
    }
}
