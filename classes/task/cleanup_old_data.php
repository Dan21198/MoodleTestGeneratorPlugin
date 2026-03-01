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
 * Scheduled task to clean up old MoodleTestGeneratorPlugin data.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen;

defined('MOODLE_INTERNAL') || die();

/**
 * Task to clean up old data.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_old_data extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanup_old_data', 'local_pdfquizgen');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        mtrace('MoodleTestGeneratorPlugin: Cleaning up old data...');

        $days = 30;
        $cutoff = time() - ($days * DAYSECS);

        // Delete old logs
        $logcount = $DB->count_records_select('local_pdfquizgen_logs', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        if ($logcount > 0) {
            $DB->delete_records_select('local_pdfquizgen_logs', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
            mtrace("Deleted $logcount old log entries.");
        }

        // Delete orphaned questions
        $orphancount = $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {local_pdfquizgen_questions} q
               LEFT JOIN {local_pdfquizgen_jobs} j ON q.jobid = j.id
              WHERE j.id IS NULL"
        );
        if ($orphancount > 0) {
            $DB->execute(
                "DELETE FROM {local_pdfquizgen_questions}
                  WHERE jobid NOT IN (SELECT id FROM {local_pdfquizgen_jobs})"
            );
            mtrace("Deleted $orphancount orphaned questions.");
        }

        // Delete old completed/failed jobs
        $oldjobs = $DB->get_records_select(
            'local_pdfquizgen_jobs',
            'timecreated < :cutoff AND status IN ("completed", "failed")',
            ['cutoff' => $cutoff],
            '',
            'id, quizid'
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

        if ($deletedjobs > 0) {
            mtrace("Deleted $deletedjobs old jobs and their quizzes.");
        }

        mtrace('Cleanup complete.');
    }
}
