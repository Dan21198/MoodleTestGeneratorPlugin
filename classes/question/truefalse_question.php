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
 * True/False question type handler.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen\question;

defined('MOODLE_INTERNAL') || die();

/**
 * Handler for creating true/false questions.
 */
class truefalse_question extends question_type_base {

    /**
     * Get the question type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'truefalse';
    }

    /**
     * Get the default penalty.
     *
     * @return float
     */
    protected function get_default_penalty(): float {
        return 1.0;
    }

    /**
     * Create type-specific question data.
     *
     * @param int $questionid The question ID
     * @param array $data The question data
     */
    protected function create_type_specific_data(int $questionid, array $data): void {
        global $DB;

        $isTrue = $this->parse_boolean_answer($data['correct_answer'] ?? '');

        // Create true answer
        $trueId = question_helper::create_answer(
            $questionid,
            get_string('true', 'qtype_truefalse'),
            $isTrue ? 1.0 : 0.0,
            '',
            FORMAT_MOODLE
        );

        // Create false answer
        $falseId = question_helper::create_answer(
            $questionid,
            get_string('false', 'qtype_truefalse'),
            $isTrue ? 0.0 : 1.0,
            '',
            FORMAT_MOODLE
        );

        // Create truefalse record
        $truefalse = new \stdClass();
        $truefalse->question = $questionid;
        $truefalse->trueanswer = $trueId;
        $truefalse->falseanswer = $falseId;
        $DB->insert_record('question_truefalse', $truefalse);
    }

    /**
     * Parse a boolean answer value.
     *
     * @param mixed $value The correct answer value
     * @return bool True if the answer is "true"
     */
    private function parse_boolean_answer($value): bool {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower($this->sanitize_text($value));
        return in_array($value, ['true', 'yes', '1', 'ano', 'pravda'], true);
    }
}

