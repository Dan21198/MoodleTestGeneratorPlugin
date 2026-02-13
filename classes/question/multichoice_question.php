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
 * Multiple choice question type handler.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen\question;

defined('MOODLE_INTERNAL') || die();

/**
 * Handler for creating multiple choice questions.
 */
class multichoice_question extends question_type_base {

    /**
     * Get the question type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'multichoice';
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

        // Create multichoice options
        $this->create_multichoice_options($questionid);

        // Create answers
        $this->create_answers($questionid, $data);
    }

    /**
     * Create multichoice options record.
     *
     * @param int $questionid Question ID
     */
    private function create_multichoice_options(int $questionid): void {
        global $DB;

        $multichoice = new \stdClass();

        // Handle different column names between Moodle versions
        if (question_helper::column_exists('qtype_multichoice_options', 'questionid')) {
            $multichoice->questionid = $questionid;
        } else {
            $multichoice->question = $questionid;
        }

        $multichoice->single = 1;
        $multichoice->shuffleanswers = 1;
        $multichoice->answernumbering = 'abc';
        $multichoice->showstandardinstruction = 0;
        $multichoice->correctfeedback = '';
        $multichoice->correctfeedbackformat = FORMAT_HTML;
        $multichoice->partiallycorrectfeedback = '';
        $multichoice->partiallycorrectfeedbackformat = FORMAT_HTML;
        $multichoice->incorrectfeedback = '';
        $multichoice->incorrectfeedbackformat = FORMAT_HTML;

        $DB->insert_record('qtype_multichoice_options', $multichoice);
    }

    /**
     * Create answer records.
     *
     * @param int $questionid Question ID
     * @param array $data Question data
     */
    private function create_answers(int $questionid, array $data): void {
        $options = $data['options'] ?? [];
        $correctAnswer = $this->parse_correct_answer($data['correct_answer'] ?? '');

        $hasCorrectAnswer = false;
        $answerRecords = [];

        foreach ($options as $index => $option) {
            $optionText = $this->sanitize_text($option);

            $isCorrect = $this->is_correct_answer($index, $optionText, $correctAnswer);
            if ($isCorrect) {
                $hasCorrectAnswer = true;
            }

            $answerRecords[] = [
                'text' => $optionText,
                'fraction' => $isCorrect ? 1.0 : 0.0
            ];
        }

        // If no correct answer was found, mark the first one as correct
        if (!$hasCorrectAnswer && !empty($answerRecords)) {
            $answerRecords[0]['fraction'] = 1.0;
        }

        // Insert all answers
        foreach ($answerRecords as $answer) {
            question_helper::create_answer(
                $questionid,
                $answer['text'],
                $answer['fraction']
            );
        }
    }

    /**
     * Check if an answer is the correct one.
     *
     * @param int $index Answer index
     * @param string $text Answer text
     * @param array $correctAnswer Parsed correct answer
     * @return bool
     */
    private function is_correct_answer(int $index, string $text, array $correctAnswer): bool {
        if ($correctAnswer['index'] !== null) {
            return $index === $correctAnswer['index'];
        }

        if ($correctAnswer['text'] !== null) {
            return $text === $correctAnswer['text'];
        }

        return false;
    }
}

