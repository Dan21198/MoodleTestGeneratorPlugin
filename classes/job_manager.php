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

global $CFG;
require_once($CFG->dirroot . '/local/pdfquizgen/lib.php');

use local_pdfquizgen\util\text_helper;

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
        $job->fileids = json_encode([$fileid]);
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
        \local_pdfquizgen_log($this->courseid, $this->userid, 'job_created', $jobid, "File: $filename");

        return $jobid;
    }

    /**
     * Create a new job with multiple files.
     *
     * @param array $fileids Array of file IDs
     * @param string $filename Combined filename description
     * @param int $questioncount Number of questions
     * @param string $questiontype Question type
     * @return int The job ID
     */
    public function create_job_multi($fileids, $filename, $questioncount, $questiontype) {
        global $DB;

        $job = new \stdClass();
        $job->courseid = $this->courseid;
        $job->userid = $this->userid;
        $job->fileid = $fileids[0]; // First file for backwards compatibility
        $job->fileids = json_encode($fileids);
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
        $filecount = count($fileids);
        \local_pdfquizgen_log($this->courseid, $this->userid, 'job_created', $jobid, "Files: $filecount, Name: $filename");

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
            // Step 1: Extract text from file(s) - supports PDF and Word documents
            $extractor = new file_extractor();

            $fileids = [];
            // Check if fileids property exists (may not exist if DB not upgraded yet)
            $rawFileids = isset($job->fileids) ? $job->fileids : null;
            if (!empty($rawFileids)) {
                $fileids = json_decode($rawFileids, true);
                if (!is_array($fileids)) {
                    $fileids = [];
                }
            }
            if (empty($fileids)) {
                // Fallback to single file
                $fileids = [$job->fileid];
            }

            // Log which files we're processing
            \local_pdfquizgen_log($this->courseid, $this->userid, 'processing_files', $jobid,
                "Processing " . count($fileids) . " files: " . implode(', ', $fileids));

            // Extract text from all files and track lengths
            $filedata = [];
            $totallength = 0;

            foreach ($fileids as $fileid) {
                $extraction = $extractor->extract_from_fileid($fileid);

                if (!$extraction['success']) {
                    $file = $DB->get_record('files', ['id' => $fileid], 'filename');
                    $filename = $file ? $file->filename : "file ID $fileid";
                    $this->fail_job($jobid, "Failed to extract from $filename: " . $extraction['error']);
                    return ['success' => false, 'error' => $extraction['error']];
                }

                $file = $DB->get_record('files', ['id' => $fileid], 'filename');
                $filename = $file ? $file->filename : "Document";
                $textlength = strlen($extraction['text']);

                $filedata[] = [
                    'fileid' => $fileid,
                    'filename' => $filename,
                    'text' => $extraction['text'],
                    'length' => $textlength
                ];

                $totallength += $textlength;
            }

            // Calculate proportional question distribution
            $totalquestions = $job->questioncount;
            $questiondistribution = $this->calculate_question_distribution($filedata, $totalquestions);

            // Log distribution for debugging
            $distlog = "Question distribution for " . count($filedata) . " files (total: $totalquestions): ";
            foreach ($filedata as $index => $data) {
                $distlog .= "{$data['filename']}={$questiondistribution[$index]}, ";
            }
            \local_pdfquizgen_log($this->courseid, $this->userid, 'question_distribution', $jobid, $distlog);

            // Save combined text for debugging
            $alltext = [];
            foreach ($filedata as $data) {
                if (count($filedata) > 1) {
                    $alltext[] = "=== Content from: {$data['filename']} ===\n" . $data['text'];
                } else {
                    $alltext[] = $data['text'];
                }
            }
            $combinedtext = implode("\n\n", $alltext);
            $this->update_job_field($jobid, 'extracted_text', $combinedtext);

            // Step 2: Generate questions using OpenRouter
            $client = new openrouter_client();
            $allquestions = [];

            // Generate questions for each file proportionally
            foreach ($filedata as $index => $data) {
                $questioncount = $questiondistribution[$index];

                // Skip files with 0 questions allocated
                if ($questioncount <= 0) {
                    \local_pdfquizgen_log($this->courseid, $this->userid, 'file_skipped', $jobid,
                        "Skipping {$data['filename']} - 0 questions allocated");
                    continue;
                }

                \local_pdfquizgen_log($this->courseid, $this->userid, 'generating_questions', $jobid,
                    "Generating $questioncount questions from {$data['filename']} (text length: {$data['length']})");

                $generation = $client->generate_questions(
                    $data['text'],
                    $questioncount,
                    $job->questiontype
                );

                // Save raw response for debugging (even if failed)
                if (!empty($generation['raw_response'])) {
                    $existingResponse = $DB->get_field('local_pdfquizgen_jobs', 'api_response', ['id' => $jobid]);
                    $newResponse = $existingResponse ? $existingResponse . "\n\n" : '';
                    $newResponse .= "=== Response for: {$data['filename']} ===\n" . $generation['raw_response'];
                    $this->update_job_field($jobid, 'api_response', $newResponse);
                }

                if (!$generation['success']) {
                    $this->fail_job($jobid, "Error for {$data['filename']}: " . $generation['error']);
                    return ['success' => false, 'error' => $generation['error']];
                }

                // Log successful generation
                $generatedcount = count($generation['questions']);
                \local_pdfquizgen_log($this->courseid, $this->userid, 'questions_generated', $jobid,
                    "Generated $generatedcount questions from {$data['filename']}");

                // Add questions from this file
                $allquestions = array_merge($allquestions, $generation['questions']);
            }

            if (empty($allquestions)) {
                $this->fail_job($jobid, 'No questions were generated from any file');
                return ['success' => false, 'error' => 'No questions generated'];
            }

            $this->update_job_field($jobid, 'api_response', json_encode($allquestions));

            // Store generated questions
            $this->store_questions($jobid, $allquestions);

            // Step 3: Create quiz
            $quizname = get_string('generated_quiz_name', 'local_pdfquizgen', [
                'filename' => basename($job->filename, '.pdf'),
                'date' => userdate(time(), '%Y-%m-%d')
            ]);

            $generator = new quiz_generator($this->courseid, $this->userid);
            $quizresult = $generator->create_quiz($allquestions, $quizname);

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
     * Calculate how many questions to generate from each file based on text length.
     *
     * @param array $filedata Array of file data with 'length' key
     * @param int $totalquestions Total number of questions to generate
     * @return array Array of question counts per file
     */
    private function calculate_question_distribution($filedata, $totalquestions) {
        $totallength = 0;
        foreach ($filedata as $data) {
            $totallength += $data['length'];
        }

        if (count($filedata) <= 1 || $totallength == 0) {
            return array_fill(0, count($filedata), $totalquestions);
        }

        $distribution = [];
        $allocated = 0;

        // First pass: allocate questions using floor (guaranteed minimum)
        foreach ($filedata as $index => $data) {
            $proportion = $data['length'] / $totallength;
            $questions = (int) floor($totalquestions * $proportion);

            // Ensure at least 1 question per file if there's substantial content
            if ($questions < 1 && $data['length'] > 500) {
                $questions = 1;
            }

            $distribution[$index] = $questions;
            $allocated += $questions;
        }

        // Second pass: distribute remaining questions to files with most content
        $remaining = $totalquestions - $allocated;
        if ($remaining > 0) {
            // Create array of indices sorted by content length (descending)
            $sortedIndices = array_keys($filedata);
            usort($sortedIndices, function($a, $b) use ($filedata) {
                return $filedata[$b]['length'] - $filedata[$a]['length'];
            });

            // Distribute remaining questions to files with most content
            $i = 0;
            while ($remaining > 0) {
                $index = $sortedIndices[$i % count($sortedIndices)];
                $distribution[$index]++;
                $remaining--;
                $i++;
            }
        }

        // Ensure no negative values (shouldn't happen, but safety check)
        foreach ($distribution as $index => $count) {
            if ($count < 0) {
                $distribution[$index] = 0;
            }
        }

        return $distribution;
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
            $value = text_helper::clean_for_database($value);
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

        \local_pdfquizgen_log($this->courseid, $this->userid, 'job_completed', $jobid, "Quiz ID: $quizid");
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
        $error = text_helper::clean_for_database($error, 65535);

        $DB->set_field('local_pdfquizgen_jobs', 'status', 'failed', ['id' => $jobid]);
        $DB->set_field('local_pdfquizgen_jobs', 'error_message', $error, ['id' => $jobid]);
        $DB->set_field('local_pdfquizgen_jobs', 'timemodified', time(), ['id' => $jobid]);

        \local_pdfquizgen_log($this->courseid, $this->userid, 'job_failed', $jobid, text_helper::truncate($error, 500));
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

        \local_pdfquizgen_log($this->courseid, $this->userid, 'job_deleted', $jobid);

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
