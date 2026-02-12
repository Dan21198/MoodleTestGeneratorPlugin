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
 * PDF Text Extractor class.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for extracting text from PDF files.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_extractor {

    /** @var int Maximum file size in MB */
    private $maxsize;

    /** @var int Maximum text length to extract */
    private $maxlength;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->maxsize = get_config('local_pdfquizgen', 'max_pdf_size') ?: 50;
        $this->maxlength = get_config('local_pdfquizgen', 'max_text_length') ?: 15000;
    }

    /**
     * Extract text from a PDF file.
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
     * @param \stored_file $file The stored file object
     * @return array Array with 'success', 'text', and 'error' keys
     */
    public function extract_from_storedfile(\stored_file $file) {
        // Check file size
        $filesize_mb = $file->get_filesize() / (1024 * 1024);
        if ($filesize_mb > $this->maxsize) {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error_file_too_large', 'local_pdfquizgen', $this->maxsize)
            ];
        }

        // Check mimetype
        if ($file->get_mimetype() !== 'application/pdf') {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error_invalid_mimetype', 'local_pdfquizgen')
            ];
        }

        // Get file content
        $content = $file->get_content();
        if (empty($content)) {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error_empty_file', 'local_pdfquizgen')
            ];
        }

        return $this->extract_from_content($content);
    }

    /**
     * Extract text from PDF content.
     *
     * @param string $content The PDF file content
     * @return array Array with 'success', 'text', and 'error' keys
     */
    public function extract_from_content($content) {
        // Try multiple extraction methods
        $text = '';

        // Method 1: Try pdftotext if available
        $text = $this->extract_with_pdftotext($content);
        if (!empty($text)) {
            return $this->format_result($text);
        }

        // Method 2: Try using Smalot PDF Parser if available
        $text = $this->extract_with_smalot($content);
        if (!empty($text)) {
            return $this->format_result($text);
        }

        // Method 3: Basic regex extraction (fallback)
        $text = $this->extract_with_regex($content);
        if (!empty($text)) {
            return $this->format_result($text);
        }

        return [
            'success' => false,
            'text' => '',
            'error' => get_string('error_extraction_failed', 'local_pdfquizgen')
        ];
    }

    /**
     * Extract text using pdftotext command-line tool.
     *
     * @param string $content The PDF content
     * @return string Extracted text or empty string on failure
     */
    private function extract_with_pdftotext($content) {
        // Check if pdftotext is available
        $pdftotext = shell_exec('which pdftotext');
        $pdftotext = trim((string)($pdftotext ?? ''));
        if (empty($pdftotext)) {
            return '';
        }

        // Create temporary files
        $tempdir = make_temp_directory('pdfquizgen');
        $pdfpath = $tempdir . '/' . uniqid('pdf_') . '.pdf';
        $txtpath = $tempdir . '/' . uniqid('txt_') . '.txt';

        // Write PDF content to temp file
        if (file_put_contents($pdfpath, $content) === false) {
            return '';
        }

        // Run pdftotext
        $command = escapeshellcmd($pdftotext) . ' ' . escapeshellarg($pdfpath) . ' ' . escapeshellarg($txtpath);
        exec($command . ' 2>&1', $output, $returncode);

        // Read extracted text
        $text = '';
        if ($returncode === 0 && file_exists($txtpath)) {
            $text = file_get_contents($txtpath);
        }

        // Clean up temp files
        @unlink($pdfpath);
        @unlink($txtpath);

        return $text;
    }

    /**
     * Extract text using Smalot PDF Parser library.
     *
     * @param string $content The PDF content
     * @return string Extracted text or empty string on failure
     */
    private function extract_with_smalot($content) {
        // Check if Smalot PDF Parser is available
        $parserpath = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($parserpath)) {
            return '';
        }

        try {
            require_once($parserpath);

            $tempdir = make_temp_directory('pdfquizgen');
            $pdfpath = $tempdir . '/' . uniqid('pdf_') . '.pdf';

            if (file_put_contents($pdfpath, $content) === false) {
                return '';
            }

            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($pdfpath);
            $text = $pdf->getText();

            @unlink($pdfpath);

            return $text;
        } catch (\Exception $e) {
            debugging('Smalot PDF Parser error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Extract text using basic regex (fallback method).
     *
     * @param string $content The PDF content
     * @return string Extracted text or empty string on failure
     */
    private function extract_with_regex($content) {
        $text = '';

        // Try to extract text streams from PDF
        // This is a basic implementation and may not work for all PDFs

        // Look for text streams
        if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                // Try to decompress if compressed
                $decompressed = @gzuncompress($stream);
                if ($decompressed !== false) {
                    $stream = $decompressed;
                }

                // Extract text between BT (Begin Text) and ET (End Text)
                if (preg_match_all('/BT\s*(.*?)\s*ET/s', $stream, $textmatches)) {
                    foreach ($textmatches[1] as $textblock) {
                        // Extract text from TJ and Tj operators
                        if (preg_match_all('/\(([^)]+)\)\s*T[jJ]/', $textblock, $tmatches)) {
                            $text .= implode(' ', $tmatches[1]) . ' ';
                        }
                    }
                }
            }
        }

        // Alternative: Look for plain text in the PDF
        if (empty($text)) {
            // Remove binary data and extract readable text
            $text = preg_replace('/[^\x20-\x7E\s]/', '', $content);
            // Clean up multiple spaces
            $text = preg_replace('/\s+/', ' ', $text);
        }

        return trim((string)($text ?? ''));
    }

    /**
     * Format the extraction result.
     *
     * @param string $text The extracted text
     * @return array Formatted result
     */
    private function format_result($text) {
        // Clean up the text
        $text = $this->clean_text($text);

        // Truncate if too long
        if (strlen($text) > $this->maxlength) {
            $text = substr($text, 0, $this->maxlength);
            // Try to end at a sentence boundary
            $lastperiod = strrpos($text, '.');
            if ($lastperiod !== false && $lastperiod > $this->maxlength * 0.8) {
                $text = substr($text, 0, $lastperiod + 1);
            }
        }

        return [
            'success' => true,
            'text' => $text,
            'error' => ''
        ];
    }

    /**
     * Clean up extracted text.
     *
     * @param string $text The raw text
     * @return string Cleaned text
     */
    private function clean_text($text) {
        if (empty($text)) {
            return '';
        }

        // Try to detect and convert encoding to UTF-8
        // Note: Only use encodings supported by the PHP installation
        $encodings = ['UTF-8', 'ASCII', 'ISO-8859-1'];

        // Check which encodings are available and add them
        $available = mb_list_encodings();
        foreach (['ISO-8859-2', 'CP1250', 'CP1252'] as $enc) {
            if (in_array($enc, $available)) {
                $encodings[] = $enc;
            }
        }

        $encoding = @mb_detect_encoding($text, $encodings, true);
        if ($encoding && $encoding !== 'UTF-8') {
            $converted = @mb_convert_encoding($text, 'UTF-8', $encoding);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        // Remove null bytes
        $text = str_replace("\0", '', $text);

        // Remove invalid UTF-8 sequences
        $text = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        if ($text === false) {
            $text = '';
        }

        // Remove BOM if present
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove excessive whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Remove control characters except newlines and tabs
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Replace any remaining problematic characters that MySQL might reject
        $text = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);

        // If preg_replace failed (invalid UTF-8), do aggressive cleanup
        if ($text === null) {
            $text = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if ($text === false) {
                $text = '';
            }
        }

        return trim((string)($text ?? ''));
    }

    /**
     * Check if PDF extraction is available.
     *
     * @return array Array with 'available' bool and 'methods' array
     */
    public function check_availability() {
        $methods = [];

        // Check pdftotext
        $pdftotext = shell_exec('which pdftotext');
        $pdftotext = trim((string)($pdftotext ?? ''));
        if (!empty($pdftotext)) {
            $methods[] = 'pdftotext';
        }

        // Check Smalot
        $parserpath = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($parserpath)) {
            $methods[] = 'smalot';
        }

        // Regex is always available as fallback
        $methods[] = 'regex (fallback)';

        return [
            'available' => !empty($methods),
            'methods' => $methods
        ];
    }
}
