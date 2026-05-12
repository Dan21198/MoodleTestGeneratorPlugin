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
 * OpenRouter API Client - DEPRECATED
 *
 * This file is kept for backwards compatibility only.
 * Use local_quizgen\llm\llm_factory instead.
 *
 * @package    local_quizgen
 * @copyright  2025 Daniel Horejski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @deprecated 2025 Use local_quizgen\llm\llm_factory instead
 */

namespace local_quizgen;

defined('MOODLE_INTERNAL') || die();

/**
 * DEPRECATED: Client for OpenRouter API - use llm_factory instead
 *
 * @package    local_quizgen
 * @copyright  2025 Daniel Horejski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @deprecated 2025 Use local_quizgen\llm\llm_factory::create() instead
 */
class openrouter_client {

    /**
     * Constructor - DEPRECATED
     *
     * @deprecated 2025 Use local_quizgen\llm\llm_factory::create() instead
     * @throws \moodle_exception
     */
    public function __construct() {
        debugging('openrouter_client is deprecated. Use local_quizgen\llm\llm_factory::create() instead', DEBUG_DEVELOPER);
        throw new \moodle_exception('error_deprecated_class', 'local_quizgen', '', 'openrouter_client');
    }
}
