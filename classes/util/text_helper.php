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
 * Text utility helper class.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for text processing utilities.
 *
 * Provides common text cleaning and encoding conversion functions.
 */
class text_helper {

    /**
     * Get list of available encodings for detection.
     * Only returns encodings that are supported by the current PHP installation.
     *
     * @return array List of encoding names
     */
    public static function get_available_encodings(): array {
        $preferred = ['UTF-8', 'ASCII', 'ISO-8859-1'];
        $optional = ['ISO-8859-2', 'CP1250', 'CP1252', 'Windows-1250', 'Windows-1252'];

        $available = mb_list_encodings();
        $result = $preferred;

        foreach ($optional as $enc) {
            if (in_array($enc, $available)) {
                $result[] = $enc;
            }
        }

        return $result;
    }

    /**
     * Clean text for database storage.
     * Removes invalid UTF-8 characters and ensures proper encoding.
     *
     * @param string $text Text to clean
     * @param int $maxlength Maximum length (0 = no limit)
     * @return string Cleaned text
     */
    public static function clean_for_database(string $text, int $maxlength = 0): string {
        if (empty($text)) {
            return '';
        }

        // Convert to UTF-8 if needed
        $encodings = self::get_available_encodings();
        $encoding = @mb_detect_encoding($text, $encodings, true);

        if ($encoding && $encoding !== 'UTF-8') {
            $converted = @mb_convert_encoding($text, 'UTF-8', $encoding);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        // Remove invalid UTF-8 sequences
        $text = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        if ($text === false) {
            $text = '';
        }

        // Remove null bytes and control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        // Replace problematic characters that MySQL might reject
        $text = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '?', $text);

        // If preg_replace failed (invalid UTF-8), try a more aggressive cleanup
        if ($text === null) {
            $text = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if ($text === false) {
                $text = '';
            }
        }

        // Truncate if needed
        if ($maxlength > 0 && mb_strlen($text, 'UTF-8') > $maxlength) {
            $text = mb_substr($text, 0, $maxlength - 3, 'UTF-8') . '...';
        }

        return $text;
    }

    /**
     * Clean extracted text from PDF.
     * More aggressive cleaning for raw PDF text.
     *
     * @param string $text Raw text from PDF
     * @return string Cleaned text
     */
    public static function clean_pdf_text(string $text): string {
        if (empty($text)) {
            return '';
        }

        // First do basic database cleaning
        $text = self::clean_for_database($text);

        // Remove BOM if present
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove excessive whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Trim
        $text = trim($text);

        return $text;
    }

    /**
     * Sanitize text for use in Moodle content.
     *
     * @param mixed $text Text to sanitize
     * @return string Sanitized text
     */
    public static function sanitize(mixed $text): string {
        return trim((string)($text ?? ''));
    }

    /**
     * Truncate text to a maximum length with ellipsis.
     *
     * @param string $text Text to truncate
     * @param int $maxlength Maximum length
     * @param string $suffix Suffix to add when truncated
     * @return string Truncated text
     */
    public static function truncate(string $text, int $maxlength, string $suffix = '...'): string {
        if (mb_strlen($text, 'UTF-8') <= $maxlength) {
            return $text;
        }

        $suffixLen = mb_strlen($suffix, 'UTF-8');
        return mb_substr($text, 0, $maxlength - $suffixLen, 'UTF-8') . $suffix;
    }
}

