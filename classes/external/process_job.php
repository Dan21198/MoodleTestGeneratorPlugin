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
 * External function to process a quiz generation job.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/pdfquizgen/lib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_course;

/**
 * External function to process a quiz generation job.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_job extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'jobid' => new external_value(PARAM_INT, 'The job ID'),
            'courseid' => new external_value(PARAM_INT, 'The course ID'),
        ]);
    }

    /**
     * Process a quiz generation job.
     *
     * @param int $jobid The job ID
     * @param int $courseid The course ID
     * @return array Result array
     */
    public static function execute($jobid, $courseid) {
        global $USER, $DB;

        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'jobid' => $jobid,
            'courseid' => $courseid,
        ]);

        $jobid = $params['jobid'];
        $courseid = $params['courseid'];

        $context = context_course::instance($courseid);
        self::validate_context($context);

        // Capability check.
        require_capability('local/pdfquizgen:use', $context);

        $job = $DB->get_record('local_pdfquizgen_jobs', [
            'id' => $jobid,
            'courseid' => $courseid,
            'userid' => $USER->id,
        ], '*', MUST_EXIST);

        if ($job->status !== 'pending') {
            return [
                'success' => false,
                'error' => get_string('job_not_pending', 'local_pdfquizgen'),
                'quizid' => 0,
                'quizurl' => '',
            ];
        }

        $jobmanager = new \local_pdfquizgen\job_manager($courseid, $USER->id);
        $result = $jobmanager->process_job($jobid);

        $quizurl = '';
        if ($result['success'] && !empty($result['cmid'])) {
            $quizurl = (new \moodle_url('/mod/quiz/view.php', ['id' => $result['cmid']]))->out(false);
        }

        return [
            'success' => $result['success'],
            'error' => $result['error'] ?? '',
            'quizid' => $result['quizid'] ?? 0,
            'quizurl' => $quizurl,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the job was processed successfully'),
            'error' => new external_value(PARAM_RAW, 'Error message if failed'),
            'quizid' => new external_value(PARAM_INT, 'The created quiz ID'),
            'quizurl' => new external_value(PARAM_RAW, 'URL to the created quiz'),
        ]);
    }
}

