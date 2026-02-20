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
 * External function to get job status.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_course;

/**
 * External function to get job status.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_job_status extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'jobid' => new external_value(PARAM_INT, 'The job ID'),
        ]);
    }

    /**
     * Get the status of a job.
     *
     * @param int $jobid The job ID
     * @return array Job status array
     */
    public static function execute($jobid) {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'jobid' => $jobid,
        ]);

        $jobid = $params['jobid'];

        $job = $DB->get_record('local_pdfquizgen_jobs', ['id' => $jobid], '*', MUST_EXIST);

        $context = context_course::instance($job->courseid);
        self::validate_context($context);

        require_capability('local/pdfquizgen:use', $context);

        if ($job->userid != $USER->id) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        $quizurl = '';
        if ($job->status === 'completed' && !empty($job->quizid)) {
            $cm = get_coursemodule_from_instance('quiz', $job->quizid, $job->courseid);
            if ($cm) {
                $quizurl = (new \moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false);
            }
        }

        return [
            'status' => $job->status,
            'quizid' => (int)($job->quizid ?? 0),
            'quizurl' => $quizurl,
            'error' => $job->error_message ?? '',
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'Job status (pending, processing, completed, failed)'),
            'quizid' => new external_value(PARAM_INT, 'The quiz ID if completed'),
            'quizurl' => new external_value(PARAM_RAW, 'URL to the quiz if completed'),
            'error' => new external_value(PARAM_RAW, 'Error message if failed'),
        ]);
    }
}

