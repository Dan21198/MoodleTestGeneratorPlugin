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
 * Base class for LLM clients.
 *
 * @package    local_quizgen
 * @copyright  2025 Daniel Horejski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizgen\llm;

defined('MOODLE_INTERNAL') || die();

/**
 * Base class for LLM clients.
 *
 * Provides common functionality like prompt building, question validation,
 * JSON parsing, etc.
 */
abstract class llm_client_base implements llm_client_interface {

    /** @var string API key or token */
    protected $apikey;

    /** @var string Model identifier */
    protected $model;

    /** @var int Maximum tokens for response */
    protected $maxtokens;

    /** @var int Request timeout in seconds */
    protected $timeout;

    /** @var int Maximum number of retries */
    protected $maxretries;

    /**
     * Constructor.
     *
     * @param string $apikey The API key
     * @param string $model The model identifier
     * @param int $maxtokens Maximum tokens
     * @param int $timeout Timeout in seconds
     * @param int $maxretries Maximum retries
     */
    public function __construct($apikey, $model, $maxtokens, $timeout, $maxretries) {
        $this->apikey = $apikey;
        $this->model = $model;
        $this->maxtokens = $maxtokens;
        $this->timeout = $timeout;
        $this->maxretries = $maxretries;
    }

    /**
     * Build system prompt for question generation.
     *
     * @param string $questiontype The question type
     * @return string The system prompt
     */
    protected function build_system_prompt($questiontype) {
        $instructions = $this->get_question_instructions($questiontype);

        return 'You are an expert educator creating quiz questions from course materials. ' .
               'Generate exactly the requested number of questions in valid JSON format. ' .
               'Use the same language as the source material. ' .
               'Each question must be clear, unambiguous, and test understanding of the material. ' .
               "\n\n" . $instructions;
    }

    /**
     * Get specific instructions for each question type.
     *
     * @param string $questiontype The question type
     * @return string The instructions
     */
    protected function get_question_instructions($questiontype) {
        switch ($questiontype) {
            case 'multichoice':
                return 'Generate multiple choice questions with 4 options each. ' .
                       'For each question provide: question (text), options (array), correct_answer (array index as 0, 1, 2, or 3), and explanation.';

            case 'truefalse':
                return 'Generate true/false questions. ' .
                       'For each question provide: question (text), correct_answer (boolean: true or false), and explanation.';

            case 'shortanswer':
                return 'Generate short answer questions. ' .
                       'For each question provide: question (text), correct_answer (primary answer text), acceptable_answers (array of alternatives), and explanation.';

            case 'mixed':
                return 'Generate a mix of multiple choice (50%), true/false (30%), and short answer (20%) questions. ' .
                       'Provide appropriate fields based on each question type.';

            default:
                return 'Generate questions with appropriate structure for each type.';
        }
    }

    /**
     * Build user prompt for question generation.
     *
     * @param string $content The text content
     * @param int $questioncount Number of questions
     * @param string $questiontype Question type
     * @return string The user prompt
     */
    protected function build_user_prompt($content, $questioncount, $questiontype) {
        $typetext = $this->get_question_type_text($questiontype);
        $jsonexample = $this->get_json_format($questiontype);

        $prompt = "Generate EXACTLY {$questioncount} {$typetext} based on the following educational content.\n\n";
        $prompt .= "CONTENT:\n" . $content . "\n\n";
        $prompt .= "CRITICAL REQUIREMENTS:\n";
        $prompt .= "1. You MUST create EXACTLY {$questioncount} questions - not {$questioncount} minus 1, not {$questioncount} plus 1, but EXACTLY {$questioncount} questions. Count them before responding.\n";
        $prompt .= "2. Questions should test understanding of key concepts from the content\n";
        $prompt .= "3. Each question must have a clear, unambiguous correct answer\n";
        $prompt .= "4. Include brief explanations for why answers are correct\n";
        $prompt .= "5. Generate all questions, answers, and explanations in the SAME LANGUAGE as the content above. If the content is in Czech, write in Czech. If in German, write in German. Match the language of the source material exactly.\n";
        $prompt .= "6. Respond ONLY with valid JSON in this exact format:\n\n";
        $prompt .= $jsonexample;
        $prompt .= "\n\nREMEMBER: The JSON must contain EXACTLY {$questioncount} questions in the 'questions' array. Verify the count before responding.";

        return $prompt;
    }

    /**
     * Get human-readable question type text.
     *
     * @param string $questiontype The question type
     * @return string Human-readable text
     */
    protected function get_question_type_text($questiontype) {
        $types = [
            'multichoice' => 'multiple choice questions with 4 options each',
            'truefalse' => 'true/false questions',
            'shortanswer' => 'short answer questions',
            'mixed' => 'mixed questions (multiple choice, true/false, and short answer)',
        ];

        return $types[$questiontype] ?? $types['multichoice'];
    }

    /**
     * Get the expected JSON format for the question type.
     *
     * @param string $questiontype The question type
     * @return string JSON format example
     */
    protected function get_json_format($questiontype) {
        if ($questiontype === 'multichoice') {
            return '{
  "questions": [
    {
      "type": "multichoice",
      "question": "First question text here?",
      "options": ["Option A", "Option B", "Option C", "Option D"],
      "correct_answer": 0,
      "explanation": "Explanation of why Option A is correct"
    },
    {
      "type": "multichoice",
      "question": "Second question text here?",
      "options": ["Option A", "Option B", "Option C", "Option D"],
      "correct_answer": 1,
      "explanation": "Explanation of why Option B is correct"
    }
  ]
}

Continue this pattern for ALL requested questions. The questions array must contain EXACTLY the number of questions requested.';
        } else if ($questiontype === 'truefalse') {
            return '{
  "questions": [
    {
      "type": "truefalse",
      "question": "First statement to evaluate?",
      "correct_answer": true,
      "explanation": "Explanation of why the statement is true"
    },
    {
      "type": "truefalse",
      "question": "Second statement to evaluate?",
      "correct_answer": false,
      "explanation": "Explanation of why the statement is false"
    }
  ]
}

Continue this pattern for ALL requested questions. The questions array must contain EXACTLY the number of questions requested.';
        } else if ($questiontype === 'shortanswer') {
            return '{
  "questions": [
    {
      "type": "shortanswer",
      "question": "First question requiring a short answer?",
      "correct_answer": "Expected answer",
      "acceptable_answers": ["alternative1", "alternative2"],
      "explanation": "Explanation of the correct answer"
    },
    {
      "type": "shortanswer",
      "question": "Second question requiring a short answer?",
      "correct_answer": "Expected answer",
      "acceptable_answers": ["alternative1"],
      "explanation": "Explanation of the correct answer"
    }
  ]
}

Continue this pattern for ALL requested questions. The questions array must contain EXACTLY the number of questions requested.';
        } else {
            // Mixed
            return '{
  "questions": [
    {
      "type": "multichoice",
      "question": "Question text here?",
      "options": ["Option A", "Option B", "Option C", "Option D"],
      "correct_answer": 0,
      "explanation": "Explanation"
    },
    {
      "type": "truefalse",
      "question": "Statement?",
      "correct_answer": true,
      "explanation": "Explanation"
    },
    {
      "type": "shortanswer",
      "question": "Question?",
      "correct_answer": "Answer",
      "acceptable_answers": ["alt1"],
      "explanation": "Explanation"
    }
  ]
}

Continue this pattern with a mix of question types for ALL requested questions. The questions array must contain EXACTLY the number of questions requested.';
        }
    }

    /**
     * Parse questions from API response.
     *
     * @param string $content The API response content
     * @return array Parsed questions with success/error flags
     */
    protected function parse_questions($content) {
        $content = trim($content);

        // Try to extract JSON from various formats
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = trim($matches[1]);
        } else if (preg_match('/```\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = trim($matches[1]);
        } else if (preg_match('/(\{[\s\S]*\})/', $content, $matches)) {
            $content = trim($matches[1]);
        }

        // Remove BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Remove comments
        $content = preg_replace('/,\s*\/\/[^\n]*\n/', ",\n", $content);
        $content = preg_replace('/\/\/[^\n]*\n/', "\n", $content);

        // Fix trailing commas
        $content = preg_replace('/,(\s*[\]\}])/', '$1', $content);

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $contentpreview = substr($content, 0, 200);
            debugging("LLM: JSON parse failed. Content preview: " . $contentpreview, DEBUG_DEVELOPER);
            return [
                'success' => false,
                'questions' => [],
                'error' => 'Failed to parse JSON: ' . json_last_error_msg()
            ];
        }

        if (!isset($data['questions']) || !is_array($data['questions'])) {
            return [
                'success' => false,
                'questions' => [],
                'error' => 'Invalid response format: questions array not found'
            ];
        }

        $validquestions = [];
        foreach ($data['questions'] as $question) {
            if ($this->validate_question($question)) {
                $validquestions[] = $question;
            }
        }

        return [
            'success' => true,
            'questions' => $validquestions,
            'error' => ''
        ];
    }

    /**
     * Validate a question structure.
     *
     * @param array $question The question array
     * @return bool True if valid
     */
    protected function validate_question($question) {
        if (!isset($question['type']) || !isset($question['question'])) {
            return false;
        }

        switch ($question['type']) {
            case 'multichoice':
                return isset($question['options']) &&
                       is_array($question['options']) &&
                       count($question['options']) >= 2 &&
                       isset($question['correct_answer']);

            case 'truefalse':
                return isset($question['correct_answer']) &&
                       is_bool($question['correct_answer']);

            case 'shortanswer':
                return isset($question['correct_answer']);

            default:
                return false;
        }
    }
}

