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
 * OpenRouter API Client class.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen;

defined('MOODLE_INTERNAL') || die();

/**
 * Client for OpenRouter API to generate quiz questions.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openrouter_client {

    /** @var string API base URL */
    private $apiurl = 'https://openrouter.ai/api/v1/chat/completions';

    /** @var string API key */
    private $apikey;

    /** @var string Model to use */
    private $model;

    /** @var int Timeout in seconds */
    private $timeout;

    /** @var int Max retries */
    private $maxretries;

    /** @var int Max tokens for response */
    private $maxtokens;

    /**
     * Constructor.
     *
     * @throws \moodle_exception If API key is not configured
     */
    public function __construct() {
        $this->apikey = get_config('local_pdfquizgen', 'openrouter_api_key');
        $this->model = $this->get_configured_model();
        $this->timeout = get_config('local_pdfquizgen', 'openrouter_timeout') ?: 60;
        $this->maxretries = get_config('local_pdfquizgen', 'max_retries') ?: 3;
        $this->maxtokens = get_config('local_pdfquizgen', 'max_tokens') ?: 2000;

        if (empty($this->apikey)) {
            throw new \moodle_exception('error_api_not_configured', 'local_pdfquizgen');
        }
    }

    /**
     * Get the configured model, handling custom model option.
     *
     * @return string The model identifier to use
     */
    private function get_configured_model() {
        $model = get_config('local_pdfquizgen', 'openrouter_model') ?: 'openai/gpt-4o-mini';

        if ($model === 'other') {
            $custommodel = get_config('local_pdfquizgen', 'openrouter_model_custom');
            if (!empty($custommodel)) {
                return trim($custommodel);
            }
            // Fallback to default if custom is empty
            return 'openai/gpt-4o-mini';
        }

        return $model;
    }

    /**
     * Generate quiz questions from text content.
     *
     * @param string $content The text content to generate questions from
     * @param int $numquestions Number of questions to generate
     * @param string $questiontype Type of questions (multichoice, truefalse, shortanswer, mixed)
     * @return array Array with 'success', 'questions', and 'error' keys
     */
    public function generate_questions($content, $numquestions = 10, $questiontype = 'multichoice') {
        $prompt = $this->build_prompt($content, $numquestions, $questiontype);

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are an expert educational content creator specializing in creating high-quality quiz questions from educational materials. You are multilingual and always generate questions in the same language as the source content provided. You must respond ONLY with valid JSON in the exact format specified. You MUST generate EXACTLY the number of questions requested - no more, no less.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ];

        $result = $this->make_request($messages);

        if (!$result['success']) {
            return $result;
        }

        $parsed = $this->parse_questions($result['content']);

        // Include raw response for debugging (truncated)
        $parsed['raw_response'] = substr($result['content'], 0, 1000);

        // Log if we got fewer questions than requested
        if ($parsed['success']) {
            $actualcount = count($parsed['questions']);
            if ($actualcount < $numquestions) {
                debugging("OpenRouter: Requested {$numquestions} questions but only got {$actualcount}", DEBUG_DEVELOPER);
            }
        }

        return $parsed;
    }

    /**
     * Build the prompt for question generation.
     *
     * @param string $content The text content
     * @param int $numquestions Number of questions
     * @param string $questiontype Question type
     * @return string The prompt
     */
    private function build_prompt($content, $numquestions, $questiontype) {
        $typetext = $this->get_question_type_text($questiontype);

        $prompt = "Generate EXACTLY {$numquestions} {$typetext} based on the following educational content.\n\n";
        $prompt .= "CONTENT:\n" . $content . "\n\n";
        $prompt .= "CRITICAL REQUIREMENTS:\n";
        $prompt .= "1. You MUST create EXACTLY {$numquestions} questions - not {$numquestions} minus 1, not {$numquestions} plus 1, but EXACTLY {$numquestions} questions. Count them before responding.\n";
        $prompt .= "2. Questions should test understanding of key concepts from the content\n";
        $prompt .= "3. Each question must have a clear, unambiguous correct answer\n";
        $prompt .= "4. Include brief explanations for why answers are correct\n";
        $prompt .= "5. Generate all questions, answers, and explanations in the SAME LANGUAGE as the content above. If the content is in Czech, write in Czech. If in German, write in German. Match the language of the source material exactly.\n";
        $prompt .= "6. Respond ONLY with valid JSON in this exact format:\n\n";

        $prompt .= $this->get_json_format($questiontype);

        $prompt .= "\n\nREMEMBER: The JSON must contain EXACTLY {$numquestions} questions in the 'questions' array. Verify the count before responding.";

        return $prompt;
    }

    /**
     * Get human-readable question type text.
     *
     * @param string $questiontype The question type
     * @return string Human-readable text
     */
    private function get_question_type_text($questiontype) {
        $types = [
            'multichoice' => 'multiple choice questions with 4 options each',
            'truefalse' => 'true/false questions',
            'shortanswer' => 'short answer questions',
            'mixed' => 'mixed questions (multiple choice, true/false, and short answer)',
        ];

        return $types[$questiontype] ?? $types['multichoice'];
    }

    /**
     * Get the expected JSON format.
     *
     * @param string $questiontype The question type
     * @return string JSON format example
     */
    private function get_json_format($questiontype) {
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
     * Check if model supports structured JSON output.
     *
     * @return bool True if model supports response_format
     */
    private function supports_json_mode() {
        // Only OpenAI models reliably support response_format with json_object
        return strpos($this->model, 'openai/') === 0;
    }

    /**
     * Make API request to OpenRouter.
     *
     * @param array $messages The messages array
     * @return array Result with 'success', 'content', and 'error' keys
     */
    private function make_request($messages) {
        debugging("OpenRouter: Preparing request to model: {$this->model}", DEBUG_DEVELOPER);

        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => (int)$this->maxtokens,
        ];

        // Only add response_format for models that support it
        if ($this->supports_json_mode()) {
            $data['response_format'] = ['type' => 'json_object'];
            debugging("OpenRouter: Using JSON mode (OpenAI model detected)", DEBUG_DEVELOPER);
        } else {
            debugging("OpenRouter: NOT using JSON mode (non-OpenAI model)", DEBUG_DEVELOPER);
        }

        $headers = [
            'Authorization: Bearer ' . $this->apikey,
            'Content-Type: application/json',
            'HTTP-Referer: ' . $GLOBALS['CFG']->wwwroot,
            'X-Title: Moodle MoodleTestGeneratorPlugin'
        ];

        $attempt = 0;
        $lasterror = '';

        while ($attempt < $this->maxretries) {
            $attempt++;
            debugging("OpenRouter: Attempt $attempt of {$this->maxretries}", DEBUG_DEVELOPER);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiurl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlerror = curl_error($ch);
            curl_close($ch);

            debugging("OpenRouter: HTTP code: $httpcode", DEBUG_DEVELOPER);

            if ($curlerror) {
                $lasterror = 'CURL Error: ' . $curlerror;
                continue;
            }

            if ($httpcode !== 200) {
                $lasterror = 'HTTP Error ' . $httpcode . ': ' . $response;
                continue;
            }

            $decoded = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $lasterror = 'JSON Decode Error: ' . json_last_error_msg();
                continue;
            }

            if (isset($decoded['error'])) {
                $lasterror = 'API Error: ' . ($decoded['error']['message'] ?? 'Unknown error');
                continue;
            }

            if (!isset($decoded['choices'][0]['message']['content'])) {
                $lasterror = 'Invalid API response structure';
                continue;
            }

            return [
                'success' => true,
                'content' => $decoded['choices'][0]['message']['content'],
                'error' => ''
            ];
        }

        return [
            'success' => false,
            'content' => '',
            'error' => $lasterror
        ];
    }

    /**
     * Parse questions from API response.
     *
     * @param string $content The API response content
     * @return array Parsed questions
     */
    private function parse_questions($content) {
        $content = trim($content);

        // Try to extract JSON from various formats
        // 1. Check for markdown code blocks with json
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = trim($matches[1]);
        }
        // 2. Check for generic markdown code blocks
        else if (preg_match('/```\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = trim($matches[1]);
        }
        // 3. Try to find JSON object starting with {
        else if (preg_match('/(\{[\s\S]*\})/', $content, $matches)) {
            $content = trim($matches[1]);
        }

        // Remove any BOM or invisible characters
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Remove JavaScript-style single-line comments (// ...)
        $content = preg_replace('/,\s*\/\/[^\n]*\n/', ",\n", $content);
        $content = preg_replace('/\/\/[^\n]*\n/', "\n", $content);

        // Remove trailing commas before ] or } (common JSON error)
        $content = preg_replace('/,(\s*[\]\}])/', '$1', $content);

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Include first part of content for debugging
            $contentpreview = substr($content, 0, 200);
            debugging("OpenRouter: JSON parse failed. Content preview: " . $contentpreview, DEBUG_DEVELOPER);
            return [
                'success' => false,
                'questions' => [],
                'error' => 'Failed to parse JSON: ' . json_last_error_msg() . ' (content starts with: ' . substr($content, 0, 100) . '...)'
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
    private function validate_question($question) {
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
                return isset($question['correct_answer']);

            case 'shortanswer':
                return isset($question['correct_answer']);

            default:
                return false;
        }
    }

    /**
     * Test API connection.
     *
     * @return array Result with 'success' and 'error' keys
     */
    public function test_connection() {
        $messages = [
            [
                'role' => 'user',
                'content' => 'Say "Connection successful" and nothing else.'
            ]
        ];

        $result = $this->make_request($messages);

        if ($result['success']) {
            return [
                'success' => true,
                'error' => ''
            ];
        }

        return $result;
    }
}
