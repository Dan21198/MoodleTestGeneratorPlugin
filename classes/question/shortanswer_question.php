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
 * Short answer question type handler.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen\question;

defined('MOODLE_INTERNAL') || die();

/**
 * Handler for creating short answer questions.
 */
class shortanswer_question extends question_type_base {

    /**
     * Get the question type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'shortanswer';
    }

    /**
     * Get the default penalty.
     *
     * @return float
     */
    protected function get_default_penalty(): float {
        return 0.3333333;
    }

    /**
     * Create type-specific question data.
     *
     * @param int $questionid The question ID
     * @param array $data The question data
     */
    protected function create_type_specific_data(int $questionid, array $data): void {
        global $DB;

        // Create shortanswer options
        $this->create_shortanswer_options($questionid);

        // Create correct answer
        $correctAnswer = $this->sanitize_text($data['correct_answer'] ?? '');
        question_helper::create_answer($questionid, $correctAnswer, 1.0, '', 0);

        // Create wildcard (catch-all incorrect answer)
        question_helper::create_answer($questionid, '*', 0.0, '', 0);
    }

    /**
     * Create shortanswer options record.
     *
     * @param int $questionid Question ID
     */
    private function create_shortanswer_options(int $questionid): void {
        global $DB;

        $options = new \stdClass();

        // Handle different column names between Moodle versions
        if (question_helper::column_exists('qtype_shortanswer_options', 'questionid')) {
            $options->questionid = $questionid;
        } else {
            $options->question = $questionid;
        }

        $options->usecase = 0;
        $DB->insert_record('qtype_shortanswer_options', $options);
    }
}

