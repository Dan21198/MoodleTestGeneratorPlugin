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
 * Question type factory.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen\question;

defined('MOODLE_INTERNAL') || die();

/**
 * Factory for creating question type handlers.
 *
 * This factory creates the appropriate question type handler based on
 * the question type string.
 */
class question_type_factory {

    /** @var array Map of question type strings to class names */
    private static $typeMap = [
        'multichoice' => multichoice_question::class,
        'truefalse' => truefalse_question::class,
        'shortanswer' => shortanswer_question::class,
    ];

    /**
     * Create a question type handler.
     *
     * @param string $type Question type (multichoice, truefalse, shortanswer)
     * @param int $categoryid Question category ID
     * @param int $userid User ID
     * @return question_type_base The question type handler
     * @throws \InvalidArgumentException If type is not supported
     */
    public static function create(string $type, int $categoryid, int $userid): question_type_base {
        $type = trim(strtolower($type));

        // Default to multichoice if type is empty or not recognized
        if (empty($type) || !isset(self::$typeMap[$type])) {
            $type = 'multichoice';
        }

        $className = self::$typeMap[$type];
        return new $className($categoryid, $userid);
    }

    /**
     * Get list of supported question types.
     *
     * @return array List of supported type identifiers
     */
    public static function get_supported_types(): array {
        return array_keys(self::$typeMap);
    }

    /**
     * Check if a question type is supported.
     *
     * @param string $type Question type
     * @return bool True if supported
     */
    public static function is_supported(string $type): bool {
        return isset(self::$typeMap[trim(strtolower($type))]);
    }

    /**
     * Register a custom question type handler.
     *
     * @param string $type Question type identifier
     * @param string $className Fully qualified class name
     */
    public static function register(string $type, string $className): void {
        self::$typeMap[trim(strtolower($type))] = $className;
    }
}

