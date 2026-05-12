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
 * @package    local_quizgen
 * @copyright  2025 Daniel Horejski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizgen\llm;

defined('MOODLE_INTERNAL') || die();

/**
 * Client for OpenRouter API to generate quiz questions.
 *
 * @package    local_quizgen
 * @copyright  2025 Daniel Horejski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openrouter_client extends llm_client_base {

    /** @var string OpenRouter API endpoint */
    const API_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    /**
     * Generate quiz questions from text content.
     *
     * @param string $content The text content to generate questions from
     * @param int $questioncount Number of questions to generate
     * @param string $questiontype Type of questions (multichoice, truefalse, shortanswer, mixed)
     * @return array Array with 'success', 'questions', 'raw_response', and 'error' keys
     */
    public function generate_questions($content, $questioncount, $questiontype) {
        $prompt = $this->build_user_prompt($content, $questioncount, $questiontype);

        $messages = [
            [
                'role' => 'system',
                'content' => $this->build_system_prompt($questiontype)
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ];

        $result = $this->make_request($messages);

        if (!$result['success']) {
            return [
                'success' => false,
                'questions' => [],
                'raw_response' => '',
                'error' => $result['error']
            ];
        }

        $parsed = $this->parse_questions($result['content']);

        // Include raw response for debugging (truncated)
        $parsed['raw_response'] = substr($result['content'], 0, 1000);

        // Log if we got fewer questions than requested
        if ($parsed['success']) {
            $actualcount = count($parsed['questions']);
            if ($actualcount < $questioncount) {
                debugging("OpenRouter: Requested {$questioncount} questions but only got {$actualcount}", DEBUG_DEVELOPER);
            }
        }

        return $parsed;
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

        // Don't use JSON mode for simple connection test
        $result = $this->make_request($messages, false);

        if ($result['success']) {
            return [
                'success' => true,
                'error' => ''
            ];
        }

        return $result;
    }

    /**
     * Get client information.
     *
     * @return array Info array
     */
    public function get_info() {
        return [
            'name' => 'OpenRouter',
            'provider' => 'openrouter.ai',
            'model' => $this->model,
            'endpoint' => self::API_ENDPOINT,
            'type' => 'remote'
        ];
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
     * @param bool $usejsonmode Whether to use JSON response format (default true)
     * @return array Result with 'success', 'content', and 'error' keys
     */
    private function make_request($messages, $usejsonmode = true) {
        debugging("OpenRouter: Preparing request to model: {$this->model}", DEBUG_DEVELOPER);

        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => (int)$this->maxtokens,
        ];

        if ($usejsonmode && $this->supports_json_mode()) {
            $data['response_format'] = ['type' => 'json_object'];
            debugging("OpenRouter: Using JSON mode (OpenAI model detected)", DEBUG_DEVELOPER);
        } else {
            debugging("OpenRouter: NOT using JSON mode", DEBUG_DEVELOPER);
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
            curl_setopt($ch, CURLOPT_URL, self::API_ENDPOINT);
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
}

