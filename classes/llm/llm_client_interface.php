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
 * Interface for LLM (Large Language Model) clients.
 *
 * @package    local_quizgen
 * @copyright  2025 Daniel Horejski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizgen\llm;

defined('MOODLE_INTERNAL') || die();

/**
 * Interface for LLM clients.
 *
 * All LLM providers must implement this interface to be compatible with the system.
 */
interface llm_client_interface {

    /**
     * Generate questions from text content.
     *
     * @param string $content The text content to generate questions from
     * @param int $questioncount Number of questions to generate
     * @param string $questiontype Type of questions (multichoice, truefalse, shortanswer, mixed)
     * @return array Result array with keys:
     *                - 'success' (bool): Whether generation was successful
     *                - 'questions' (array): Generated questions
     *                - 'raw_response' (string): Raw API response for debugging
     *                - 'error' (string): Error message if applicable
     */
    public function generate_questions($content, $questioncount, $questiontype);

    /**
     * Test the API connection.
     *
     * @return array Result array with keys:
     *                - 'success' (bool): Whether connection test passed
     *                - 'error' (string): Error message if applicable
     */
    public function test_connection();

    /**
     * Get information about the client.
     *
     * @return array Array with provider information
     */
    public function get_info();
}

