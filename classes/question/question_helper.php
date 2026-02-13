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
 * Question helper class for common question operations.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen\question;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for common question operations.
 *
 * This class provides utilities for:
 * - Moodle version detection
 * - Question bank entry management
 * - Question version management
 * - Common question field initialization
 */
class question_helper {

    /** @var bool|null Cached Moodle 4+ detection result */
    private static $is_moodle4 = null;

    /**
     * Check if we're running on Moodle 4.0+ (uses question bank API).
     *
     * @return bool True if Moodle 4.0+
     */
    public static function is_moodle4(): bool {
        global $DB;

        if (self::$is_moodle4 === null) {
            try {
                $DB->get_record_sql("SELECT id FROM {question_bank_entries} LIMIT 1", [], IGNORE_MISSING);
                self::$is_moodle4 = true;
            } catch (\Exception $e) {
                try {
                    $dbman = $DB->get_manager();
                    self::$is_moodle4 = $dbman->table_exists('question_bank_entries');
                } catch (\Exception $e2) {
                    self::$is_moodle4 = false;
                }
            }
        }

        return self::$is_moodle4;
    }

    /**
     * Create a question bank entry for Moodle 4+.
     *
     * @param int $categoryid The question category ID
     * @param int $userid The user creating the entry
     * @return object|null The question bank entry object or null for pre-Moodle 4
     */
    public static function create_question_bank_entry(int $categoryid, int $userid): ?object {
        global $DB;

        if (!self::is_moodle4()) {
            return null;
        }

        $qbe = new \stdClass();
        $qbe->questioncategoryid = $categoryid;
        $qbe->idnumber = null;
        $qbe->ownerid = $userid;
        $qbe->id = $DB->insert_record('question_bank_entries', $qbe);

        return $qbe;
    }

    /**
     * Create a question version record for Moodle 4+.
     *
     * @param object $qbe The question bank entry object
     * @param int $questionid The question ID
     * @return object|null The question version object or null
     */
    public static function create_question_version(object $qbe, int $questionid): ?object {
        global $DB;

        if (!self::is_moodle4() || !$qbe) {
            return null;
        }

        $qv = new \stdClass();
        $qv->questionbankentryid = $qbe->id;
        $qv->questionid = $questionid;
        $qv->version = 1;
        $qv->status = 'ready';
        $qv->id = $DB->insert_record('question_versions', $qv);

        return $qv;
    }

    /**
     * Initialize base question object with common fields.
     *
     * @param string $qtype Question type
     * @param string $questiontext Question text
     * @param string $explanation General feedback/explanation
     * @param int $userid User ID
     * @param int|null $categoryid Category ID (for pre-Moodle 4)
     * @param float $defaultmark Default mark
     * @param float $penalty Penalty
     * @return object The question object
     */
    public static function create_base_question(
        string $qtype,
        string $questiontext,
        string $explanation,
        int $userid,
        ?int $categoryid = null,
        float $defaultmark = 1.0,
        float $penalty = 0.3333333
    ): object {
        $question = new \stdClass();
        $question->qtype = $qtype;
        $question->name = self::generate_question_name($questiontext);
        $question->questiontext = trim($questiontext);
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = trim($explanation);
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = $defaultmark;
        $question->penalty = $penalty;
        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $userid;
        $question->modifiedby = $userid;

        // Pre-Moodle 4 fields
        if (!self::is_moodle4() && $categoryid !== null) {
            $question->category = $categoryid;
            $question->version = make_unique_id_code();
            $question->hidden = 0;
        }

        return $question;
    }

    /**
     * Generate a short name for a question.
     *
     * @param string $questiontext Full question text
     * @param int $maxlength Maximum length of the name
     * @return string Generated question name
     */
    public static function generate_question_name(string $questiontext, int $maxlength = 50): string {
        $text = trim($questiontext);
        $name = strip_tags($text);

        if (strlen($name) > $maxlength) {
            $name = substr($name, 0, $maxlength - 3) . '...';
        }

        if (empty($name)) {
            $name = 'Question ' . time();
        }

        return $name;
    }

    /**
     * Create an answer record.
     *
     * @param int $questionid Question ID
     * @param string $answertext Answer text
     * @param float $fraction Grade fraction (0.0 to 1.0)
     * @param string $feedback Feedback text
     * @param int $answerformat Answer format (default: FORMAT_HTML = 1)
     * @return int The answer ID
     */
    public static function create_answer(
        int $questionid,
        string $answertext,
        float $fraction = 0.0,
        string $feedback = '',
        int $answerformat = 1
    ): int {
        global $DB;

        $answer = new \stdClass();
        $answer->question = $questionid;
        $answer->answer = $answertext;
        $answer->answerformat = $answerformat;
        $answer->fraction = $fraction;
        $answer->feedback = $feedback;
        $answer->feedbackformat = FORMAT_HTML;

        return $DB->insert_record('question_answers', $answer);
    }

    /**
     * Check if a table column exists.
     *
     * @param string $table Table name
     * @param string $column Column name
     * @return bool True if column exists
     */
    public static function column_exists(string $table, string $column): bool {
        global $DB;

        $columns = $DB->get_columns($table);
        return isset($columns[$column]);
    }
}

