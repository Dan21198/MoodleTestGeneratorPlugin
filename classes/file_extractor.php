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
 * File Text Extractor coordinator class.
 *
 * This class coordinates between specialized extractors (PDF, Word)
 * based on the file type.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen;

defined('MOODLE_INTERNAL') || die();

/**
 * Coordinates text extraction from various document formats.
 *
 * Delegates to specialized extractors:
 * - pdf_extractor for PDF files
 * - word_extractor for Word documents (DOCX, DOC)
 */
class file_extractor {

    /** @var pdf_extractor PDF extractor instance */
    private $pdfExtractor;

    /** @var word_extractor Word extractor instance */
    private $wordExtractor;

    /** @var int Maximum file size in MB */
    private $maxsize;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->maxsize = get_config('local_pdfquizgen', 'max_pdf_size') ?: 50;
        $this->pdfExtractor = new pdf_extractor();
        $this->wordExtractor = new word_extractor();
    }

    /**
     * Get all supported MIME types.
     *
     * @return array Array of supported MIME types
     */
    public static function get_supported_mimetypes() {
        return array_merge(
            ['application/pdf'],
            word_extractor::SUPPORTED_MIMETYPES
        );
    }

    /**
     * Check if a mimetype is supported.
     *
     * @param string $mimetype The MIME type to check
     * @return bool True if supported
     */
    public static function is_supported_mimetype($mimetype) {
        return in_array($mimetype, self::get_supported_mimetypes());
    }

    /**
     * Check if the mimetype is a PDF.
     *
     * @param string $mimetype The MIME type to check
     * @return bool True if PDF
     */
    public static function is_pdf($mimetype) {
        return $mimetype === 'application/pdf';
    }

    /**
     * Check if the mimetype is a Word document.
     *
     * @param string $mimetype The MIME type to check
     * @return bool True if Word document
     */
    public static function is_word($mimetype) {
        return word_extractor::is_supported_mimetype($mimetype);
    }

    /**
     * Extract text from a file by ID.
     *
     * @param int $fileid The file ID in mdl_files
     * @return array Array with 'success', 'text', and 'error' keys
     */
    public function extract_from_fileid($fileid) {
        global $DB;

        $file = $DB->get_record('files', ['id' => $fileid]);
        if (!$file) {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error_file_not_found', 'local_pdfquizgen')
            ];
        }

        $fs = get_file_storage();
        $fileobj = $fs->get_file_by_id($fileid);

        if (!$fileobj) {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error_file_not_found', 'local_pdfquizgen')
            ];
        }

        return $this->extract_from_storedfile($fileobj);
    }

    /**
     * Extract text from a stored_file object.
     *
     * Delegates to the appropriate specialized extractor based on file type.
     *
     * @param \stored_file $file The stored file object
     * @return array Array with 'success', 'text', and 'error' keys
     */
    public function extract_from_storedfile(\stored_file $file) {
        $filesizeMb = $file->get_filesize() / (1024 * 1024);
        if ($filesizeMb > $this->maxsize) {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error_file_too_large', 'local_pdfquizgen', $this->maxsize)
            ];
        }

        $mimetype = $file->get_mimetype();

        if (!self::is_supported_mimetype($mimetype)) {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error_invalid_mimetype', 'local_pdfquizgen')
            ];
        }

        // Delegate to appropriate extractor.
        if (self::is_pdf($mimetype)) {
            return $this->pdfExtractor->extract_from_storedfile($file);
        }

        if (self::is_word($mimetype)) {
            return $this->wordExtractor->extract_from_storedfile($file);
        }

        // Should not reach here, but just in case.
        return [
            'success' => false,
            'text' => '',
            'error' => get_string('error_invalid_mimetype', 'local_pdfquizgen')
        ];
    }
}
