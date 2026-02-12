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
 * Job Manager class.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen;

defined('MOODLE_INTERNAL') || die();

/**
 * Manager for PDF to Quiz generation jobs.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_manager {

    /** @var int Course ID */
    private $courseid;

    /** @var int User ID */
    private $userid;

    /**
     * Constructor.
     *
     * @param int $courseid The course ID
     * @param int $userid The user ID
     */
    public function __construct($courseid, $userid) {
        $this->courseid = $courseid;
        $this->userid = $userid;
    }

    /**
     * Create a new job.
     *
     * @param int $fileid The file ID
     * @param string $filename The filename
     * @param int $questioncount Number of questions
     * @param string $questiontype Question type
     * @return int The job ID
     */
    public function create_job($fileid, $filename, $questioncount, $questiontype) {
        global $DB;

        $job = new \stdClass();
        $job->courseid = $this->courseid;
        $job->userid = $this->userid;
        $job->fileid = $fileid;
        $job->filename = $filename;
        $job->status = 'pending';
        $job->quizid = null;
        $job->questioncount = $questioncount;
        $job->questiontype = $questiontype;
        $job->extracted_text = null;
        $job->api_response = null;
        $job->error_message = null;
        $job->timecreated = time();
        $job->timemodified = time();
        $job->timecompleted = null;

        $jobid = $DB->insert_record('local_pdfquizgen_jobs', $job);

        // Log the action
        local_pdfquizgen_log($this->courseid, $this->userid, 'job_created', $jobid, "File: $filename");

        return $jobid;
    }

    /**
     * Process a job.
     *
     * @param int $jobid The job ID
     * @return array Result with 'success' and 'error' keys
     */
    public function process_job($jobid) {
        global $DB;

        $job = $DB->get_record('local_pdfquizgen_jobs', ['id' => $jobid]);
        if (!$job) {
            return ['success' => false, 'error' => 'Job not found'];
        }

        // Update status to processing
        $this->update_job_status($jobid, 'processing');

        try {
            // Step 1: Extract text from PDF
            $extractor = new pdf_extractor();
            $extraction = $extractor->extract_from_fileid($job->fileid);

            if (!$extraction['success']) {
                $this->fail_job($jobid, $extraction['error']);
                return ['success' => false, 'error' => $extraction['error']];
            }

            $this->update_job_field($jobid, 'extracted_text', $extraction['text']);

            // Step 2: Generate questions using OpenRouter
            $client = new openrouter_client();
            $generation = $client->generate_questions(
                $extraction['text'],
                $job->questioncount,
                $job->questiontype
            );

            if (!$generation['success']) {
                $this->fail_job($jobid, $generation['error']);
                return ['success' => false, 'error' => $generation['error']];
            }

            $this->update_job_field($jobid, 'api_response', json_encode($generation['questions']));

            // Store generated questions
            $this->store_questions($jobid, $generation['questions']);

            // Step 3: Create quiz
            $quizname = get_string('generated_quiz_name', 'local_pdfquizgen', [
                'filename' => basename($job->filename, '.pdf'),
                'date' => userdate(time(), '%Y-%m-%d')
            ]);

            $generator = new quiz_generator($this->courseid, $this->userid);
            $quizresult = $generator->create_quiz($generation['questions'], $quizname);

            if (!$quizresult['success']) {
                $this->fail_job($jobid, $quizresult['error']);
                return ['success' => false, 'error' => $quizresult['error']];
            }

            // Update job as completed
            $this->complete_job($jobid, $quizresult['quizid']);

            // Update stored questions with moodle question IDs
            $this->update_question_moodle_ids($jobid, $quizresult['questioncount']);

            return [
                'success' => true,
                'quizid' => $quizresult['quizid'],
                'cmid' => $quizresult['cmid'],
                'questioncount' => $quizresult['questioncount']
            ];

        } catch (\Exception $e) {
            $error = 'Exception: ' . $e->getMessage();
            $this->fail_job($jobid, $error);
            return ['success' => false, 'error' => $error];
        }
    }

    /**
     * Store generated questions in database.
     *
     * @param int $jobid The job ID
     * @param array $questions Array of question data
     */
    private function store_questions($jobid, $questions) {
        global $DB;

        foreach ($questions as $question) {
            $record = new \stdClass();
            $record->jobid = $jobid;
            $record->questiontext = $question['question'];
            $record->questiontype = $question['type'];
            $record->options = isset($question['options']) ? json_encode($question['options']) : null;
            $record->correctanswer = is_array($question['correct_answer']) ?
                json_encode($question['correct_answer']) : $question['correct_answer'];
            $record->explanation = $question['explanation'] ?? '';
            $record->moodle_questionid = null;
            $record->timecreated = time();

            $DB->insert_record('local_pdfquizgen_questions', $record);
        }
    }

    /**
     * Update Moodle question IDs after quiz creation.
     *
     * @param int $jobid The job ID
     * @param int $questioncount Number of questions created
     */
    private function update_question_moodle_ids($jobid, $questioncount) {
        global $DB;

        $job = $DB->get_record('local_pdfquizgen_jobs', ['id' => $jobid]);
        if (!$job || !$job->quizid) {
            return;
        }

        // Get quiz slots to find question IDs
        $slots = $DB->get_records('quiz_slots', ['quizid' => $job->quizid], 'slot ASC');

        $storedquestions = $DB->get_records('local_pdfquizgen_questions', ['jobid' => $jobid], 'id ASC');

        $i = 0;
        foreach ($storedquestions as $stored) {
            if (isset($slots[$i + 1])) {
                $stored->moodle_questionid = $slots[$i + 1]->questionid;
                $DB->update_record('local_pdfquizgen_questions', $stored);
            }
            $i++;
        }
    }

    /**
     * Update job status.
     *
     * @param int $jobid The job ID
     * @param string $status New status
     */
    private function update_job_status($jobid, $status) {
        global $DB;

        $DB->set_field('local_pdfquizgen_jobs', 'status', $status, ['id' => $jobid]);
        $DB->set_field('local_pdfquizgen_jobs', 'timemodified', time(), ['id' => $jobid]);
    }

    /**
     * Update a job field.
     *
     * @param int $jobid The job ID
     * @param string $field Field name
     * @param mixed $value Field value
     */
    private function update_job_field($jobid, $field, $value) {
        global $DB;

        // Clean text fields before saving to database
        if (is_string($value) && in_array($field, ['extracted_text', 'api_response', 'error_message'])) {
            $value = $this->clean_text_for_db($value);
        }

        $DB->set_field('local_pdfquizgen_jobs', $field, $value, ['id' => $jobid]);
    }

    /**
     * Mark job as completed.
     *
     * @param int $jobid The job ID
     * @param int $quizid The created quiz ID
     */
    private function complete_job($jobid, $quizid) {
        global $DB;

        $DB->set_field('local_pdfquizgen_jobs', 'status', 'completed', ['id' => $jobid]);
        $DB->set_field('local_pdfquizgen_jobs', 'quizid', $quizid, ['id' => $jobid]);
        $DB->set_field('local_pdfquizgen_jobs', 'timecompleted', time(), ['id' => $jobid]);
        $DB->set_field('local_pdfquizgen_jobs', 'timemodified', time(), ['id' => $jobid]);

        local_pdfquizgen_log($this->courseid, $this->userid, 'job_completed', $jobid, "Quiz ID: $quizid");
    }

    /**
     * Mark job as failed.
     *
     * @param int $jobid The job ID
     * @param string $error Error message
     */
    private function fail_job($jobid, $error) {
        global $DB;

        // Clean and truncate error message to prevent DB errors
        $error = $this->clean_text_for_db($error, 65535);

        $DB->set_field('local_pdfquizgen_jobs', 'status', 'failed', ['id' => $jobid]);
        $DB->set_field('local_pdfquizgen_jobs', 'error_message', $error, ['id' => $jobid]);
        $DB->set_field('local_pdfquizgen_jobs', 'timemodified', time(), ['id' => $jobid]);

        local_pdfquizgen_log($this->courseid, $this->userid, 'job_failed', $jobid, substr($error, 0, 500));
    }

    /**
     * Clean text for database storage - removes invalid UTF-8 characters.
     *
     * @param string $text Text to clean
     * @param int $maxlength Maximum length (0 = no limit)
     * @return string Cleaned text
     */
    private function clean_text_for_db($text, $maxlength = 0) {
        if (empty($text)) {
            return '';
        }

        // Convert to UTF-8 if needed - use only supported encodings
        $encodings = ['UTF-8', 'ASCII', 'ISO-8859-1'];
        $available = mb_list_encodings();
        foreach (['ISO-8859-2', 'CP1250', 'CP1252'] as $enc) {
            if (in_array($enc, $available)) {
                $encodings[] = $enc;
            }
        }

        $encoding = @mb_detect_encoding($text, $encodings, true);
        if ($encoding && $encoding !== 'UTF-8') {
            $text = @mb_convert_encoding($text, 'UTF-8', $encoding);
            if ($text === false) {
                $text = '';
            }
        }

        // Remove invalid UTF-8 sequences
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // Remove any remaining non-UTF8 characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        // Replace problematic characters that MySQL might reject
        $text = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '?', $text);

        // If preg_replace failed (invalid UTF-8), try a more aggressive cleanup
        if ($text === null) {
            $text = iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if ($text === false) {
                $text = '';
            }
        }

        // Truncate if needed
        if ($maxlength > 0 && mb_strlen($text, 'UTF-8') > $maxlength) {
            $text = mb_substr($text, 0, $maxlength - 3, 'UTF-8') . '...';
        }

        return $text;
    }

    /**
     * Get job details.
     *
     * @param int $jobid The job ID
     * @return object|false Job record or false
     */
    public function get_job($jobid) {
        global $DB;
        return $DB->get_record('local_pdfquizgen_jobs', ['id' => $jobid]);
    }

    /**
     * Get jobs for a course.
     *
     * @param int $limit Number of jobs to return
     * @return array Array of job records
     */
    public function get_course_jobs($limit = 50) {
        global $DB;

        return $DB->get_records(
            'local_pdfquizgen_jobs',
            ['courseid' => $this->courseid],
            'timecreated DESC',
            '*',
            0,
            $limit
        );
    }

    /**
     * Delete a job and its associated data.
     *
     * @param int $jobid The job ID
     * @return bool True on success
     */
    public function delete_job($jobid) {
        global $DB;

        $job = $DB->get_record('local_pdfquizgen_jobs', ['id' => $jobid]);
        if (!$job) {
            return false;
        }

        // Delete associated quiz if exists
        if ($job->quizid) {
            $generator = new quiz_generator($this->courseid, $this->userid);
            $generator->delete_quiz($job->quizid);
        }

        // Delete stored questions
        $DB->delete_records('local_pdfquizgen_questions', ['jobid' => $jobid]);

        // Delete job
        $DB->delete_records('local_pdfquizgen_jobs', ['id' => $jobid]);

        local_pdfquizgen_log($this->courseid, $this->userid, 'job_deleted', $jobid);

        return true;
    }

    /**
     * Retry a failed job.
     *
     * @param int $jobid The job ID
     * @return array Result with 'success' and 'error' keys
     */
    public function retry_job($jobid) {
        global $DB;

        $job = $DB->get_record('local_pdfquizgen_jobs', ['id' => $jobid]);
        if (!$job) {
            return ['success' => false, 'error' => 'Job not found'];
        }

        if ($job->status !== 'failed') {
            return ['success' => false, 'error' => 'Only failed jobs can be retried'];
        }

        // Reset job status
        $DB->set_field('local_pdfquizgen_jobs', 'status', 'pending', ['id' => $jobid]);
        $DB->set_field('local_pdfquizgen_jobs', 'error_message', null, ['id' => $jobid]);
        $DB->set_field('local_pdfquizgen_jobs', 'timemodified', time(), ['id' => $jobid]);

        // Delete old stored questions
        $DB->delete_records('local_pdfquizgen_questions', ['jobid' => $jobid]);

        // Process the job
        return $this->process_job($jobid);
    }

    /**
     * Get job statistics.
     *
     * @return array Statistics array
     */
    public function get_statistics() {
        global $DB;

        $stats = [
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0
        ];

        $counts = $DB->get_records_sql(
            "SELECT status, COUNT(*) as count
               FROM {local_pdfquizgen_jobs}
              WHERE courseid = ?
           GROUP BY status",
            [$this->courseid]
        );

        foreach ($counts as $row) {
            $stats[$row->status] = (int)$row->count;
            $stats['total'] += (int)$row->count;
        }

        return $stats;
    }
}
