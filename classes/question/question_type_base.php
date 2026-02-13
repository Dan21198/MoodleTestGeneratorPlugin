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
 * Abstract base class for question type handlers.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen\question;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract base class for question type handlers.
 *
 * Each question type (multichoice, truefalse, shortanswer) extends this class
 * and implements the specific creation logic.
 */
abstract class question_type_base {

    /** @var int User ID */
    protected $userid;

    /** @var int Category ID */
    protected $categoryid;

    /**
     * Constructor.
     *
     * @param int $categoryid Question category ID
     * @param int $userid User ID creating the question
     */
    public function __construct(int $categoryid, int $userid) {
        $this->categoryid = $categoryid;
        $this->userid = $userid;
    }

    /**
     * Get the question type identifier.
     *
     * @return string Question type (e.g., 'multichoice', 'truefalse')
     */
    abstract public function get_type(): string;

    /**
     * Get the default penalty for this question type.
     *
     * @return float Penalty value
     */
    abstract protected function get_default_penalty(): float;

    /**
     * Create type-specific question data.
     *
     * @param int $questionid The question ID
     * @param array $data The question data
     * @return void
     */
    abstract protected function create_type_specific_data(int $questionid, array $data): void;

    /**
     * Create a question.
     *
     * @param array $data Question data including 'question', 'explanation', etc.
     * @return int The created question ID
     */
    public function create(array $data): int {
        global $DB;

        // Extract and sanitize common fields
        $questiontext = $this->sanitize_text($data['question'] ?? '');
        $explanation = $this->sanitize_text($data['explanation'] ?? '');

        // Create question bank entry for Moodle 4+
        $qbe = question_helper::create_question_bank_entry($this->categoryid, $this->userid);

        // Create base question
        $question = question_helper::create_base_question(
            $this->get_type(),
            $questiontext,
            $explanation,
            $this->userid,
            $this->categoryid,
            1.0,
            $this->get_default_penalty()
        );

        // Insert question
        $question->id = $DB->insert_record('question', $question);

        // Create version record for Moodle 4+
        if ($qbe) {
            question_helper::create_question_version($qbe, $question->id);
        }

        // Create type-specific data (answers, options, etc.)
        $this->create_type_specific_data($question->id, $data);

        return $question->id;
    }

    /**
     * Sanitize text input.
     *
     * @param mixed $text Text to sanitize
     * @return string Sanitized text
     */
    protected function sanitize_text($text): string {
        return trim((string)($text ?? ''));
    }

    /**
     * Parse correct answer value.
     *
     * @param mixed $value The correct answer value
     * @return array ['index' => int|null, 'text' => string|null]
     */
    protected function parse_correct_answer($value): array {
        if (is_int($value)) {
            return ['index' => $value, 'text' => null];
        }

        if (is_numeric($value)) {
            return ['index' => (int)$value, 'text' => null];
        }

        return ['index' => null, 'text' => $this->sanitize_text($value)];
    }
}

