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

use local_pdfquizgen\util\text_helper;
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
     * @param string $content PDF file content
     * @param string $filename Original filename for context
     * @return array Array with 'success', 'text', and 'error' keys
     */
    public function extract_from_content($content, $filename = 'document.pdf') {
        // Try different extraction methods in order of reliability
        $text = '';
        $methods = [];

        // Method 1: Try pdftotext (most reliable if available)
        $text = $this->extract_with_pdftotext($content);
        if (!empty($text)) {
            $methods[] = 'pdftotext';
        }

        // Method 2: Try Smalot PDF Parser if available
        if (empty($text)) {
            $text = $this->extract_with_smalot($content);
            if (!empty($text)) {
                $methods[] = 'smalot';
            }
        }

        // Method 3: Try to extract text from PDF streams
        if (empty($text)) {
            $text = $this->extract_from_streams($content);
            if (!empty($text)) {
                $methods[] = 'streams';
            }
        }

        // Method 4: Try basic text extraction from PDF
        if (empty($text)) {
            $text = $this->extract_basic($content);
            if (!empty($text)) {
                $methods[] = 'basic';
            }
        }

        // Validate extracted text - make sure it's not just PDF metadata
        if (!$this->is_valid_extracted_text($text)) {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error_no_text_extracted', 'local_pdfquizgen')
            ];
        }

        return $this->format_result($text, $methods);
    }

    /**
     * Extract text using pdftotext command line tool.
     *
     * @param string $content The PDF content
     * @return string Extracted text or empty string on failure
     */
    private function extract_with_pdftotext($content) {
        // Check if pdftotext is available
        $pdftotext = '';
        if (PHP_OS_FAMILY === 'Windows') {
            exec('where pdftotext 2>nul', $output, $returnVar);
            $pdftotext = $returnVar === 0 ? 'pdftotext' : '';
        } else {
            $pdftotext = trim((string)shell_exec('which pdftotext 2>/dev/null'));
        }

        if (empty($pdftotext)) {
            return '';
        }

        try {
            $tempdir = make_temp_directory('pdfquizgen');
            $pdfpath = $tempdir . '/' . uniqid('pdf_') . '.pdf';
            $txtpath = $tempdir . '/' . uniqid('txt_') . '.txt';

            if (file_put_contents($pdfpath, $content) === false) {
                return '';
            }

            // Run pdftotext
            $cmd = escapeshellcmd($pdftotext) . ' -enc UTF-8 -layout '
                 . escapeshellarg($pdfpath) . ' '
                 . escapeshellarg($txtpath) . ' 2>&1';

            exec($cmd, $output, $returnVar);

            $text = '';
            if ($returnVar === 0 && file_exists($txtpath)) {
                $text = file_get_contents($txtpath);
            }

            // Cleanup
            @unlink($pdfpath);
            @unlink($txtpath);

            return $text ?: '';
        } catch (\Exception $e) {
            // pdftotext failed, will try other methods
            return '';
        }
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
            // Smalot parser failed, will try other methods
            return '';
        }
    }

    /**
     * Extract text using basic extraction method.
     * This method tries to extract text from uncompressed PDF content.
     *
     * @param string $content The PDF content
     * @return string Extracted text or empty string on failure
     */
    private function extract_basic($content) {
        $text = '';

        // First, try to extract from uncompressed BT/ET blocks in the raw content
        if (preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $matches)) {
            foreach ($matches[1] as $textblock) {
                $extracted = $this->extract_text_from_block($textblock);
                if (!empty($extracted)) {
                    $text .= $extracted . ' ';
                }
            }
        }

        // If we found actual text content, return it
        if (!empty(trim($text)) && strlen(trim($text)) > 50) {
            return trim($text);
        }

        // Don't return raw PDF content - it's useless
        return '';
    }

    /**
     * Extract text from a PDF text block (content between BT and ET).
     *
     * @param string $textblock The text block content
     * @return string Extracted text
     */
    private function extract_text_from_block(string $textblock): string {
        $text = '';

        // Extract text from TJ operator (array of strings)
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $textblock, $tjMatches)) {
            foreach ($tjMatches[1] as $tjContent) {
                // Extract strings from TJ array
                if (preg_match_all('/\(([^)]*)\)/', $tjContent, $strings)) {
                    $text .= implode('', $strings[1]);
                }
            }
        }

        // Extract text from Tj operator (single string)
        if (preg_match_all('/\(([^)]*)\)\s*Tj/', $textblock, $tjMatches)) {
            $text .= implode('', $tjMatches[1]);
        }

        // Extract text from ' operator (move to next line and show text)
        if (preg_match_all('/\(([^)]*)\)\s*\'/', $textblock, $quoteMatches)) {
            $text .= "\n" . implode("\n", $quoteMatches[1]);
        }

        // Handle hex strings
        if (preg_match_all('/<([0-9A-Fa-f]+)>\s*T[jJ]/', $textblock, $hexMatches)) {
            foreach ($hexMatches[1] as $hex) {
                $text .= $this->decode_hex_string($hex);
            }
        }

        // Decode PDF escape sequences
        $text = $this->decode_pdf_escapes($text);

        return $text;
    }

    /**
     * Decode PDF hex string to text.
     *
     * @param string $hex Hex string
     * @return string Decoded text
     */
    private function decode_hex_string(string $hex): string {
        $text = '';
        $len = strlen($hex);

        for ($i = 0; $i < $len; $i += 2) {
            $charCode = hexdec(substr($hex, $i, 2));
            if ($charCode >= 32 && $charCode <= 126) {
                $text .= chr($charCode);
            } elseif ($charCode === 10 || $charCode === 13) {
                $text .= "\n";
            } else {
                $text .= ' ';
            }
        }

        return $text;
    }

    /**
     * Decode PDF escape sequences.
     *
     * @param string $text Text with PDF escapes
     * @return string Decoded text
     */
    private function decode_pdf_escapes(string $text): string {
        $replacements = [
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\b' => "\b",
            '\\f' => "\f",
            '\\(' => '(',
            '\\)' => ')',
            '\\\\' => '\\',
        ];

        $text = str_replace(array_keys($replacements), array_values($replacements), $text);

        // Handle octal escapes \ddd
        $text = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($matches) {
            return chr(octdec($matches[1]));
        }, $text);

        return $text;
    }

    /**
     * Extract text from PDF streams.
     * Handles compressed (FlateDecode) and uncompressed streams.
     *
     * @param string $content The PDF content
     * @return string Extracted text or empty string on failure
     */
    private function extract_from_streams($content) {
        $text = '';
        $processedStreams = 0;

        // Find all streams
        if (!preg_match_all('/stream\s*\r?\n(.*?)\r?\nendstream/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        foreach ($matches[1] as $index => $match) {
            $stream = $match[0];
            $offset = $match[1];

            // Find the object definition before this stream to check for filters
            $objStart = strrpos(substr($content, 0, $offset), '<<');
            $objDict = '';
            if ($objStart !== false) {
                $objEnd = strpos($content, '>>', $objStart);
                if ($objEnd !== false && $objEnd < $offset) {
                    $objDict = substr($content, $objStart, $objEnd - $objStart + 2);
                }
            }

            // Try to decompress the stream
            $decompressed = $this->decompress_stream($stream, $objDict);

            // Extract text from the decompressed content
            $extracted = $this->extract_text_from_stream($decompressed);

            if (!empty($extracted)) {
                $text .= $extracted . "\n";
                $processedStreams++;
            }
        }

        // If we couldn't extract meaningful text, return empty
        if (strlen(trim($text)) < 50 || $processedStreams === 0) {
            return '';
        }

        return trim($text);
    }

    /**
     * Decompress a PDF stream based on its filter.
     *
     * @param string $stream Raw stream content
     * @param string $objDict Object dictionary (to check for filters)
     * @return string Decompressed content
     */
    private function decompress_stream(string $stream, string $objDict): string {
        // Check if FlateDecode filter is used
        $isFlate = stripos($objDict, '/FlateDecode') !== false ||
                   stripos($objDict, '/Fl') !== false;

        if ($isFlate) {
            // Try gzinflate first (raw deflate without header)
            $result = @gzinflate($stream);
            if ($result !== false) {
                return $result;
            }

            // Try with different window sizes
            for ($wbits = 15; $wbits >= 8; $wbits--) {
                $result = @gzinflate($stream, $wbits);
                if ($result !== false) {
                    return $result;
                }
            }

            // Try gzuncompress (zlib format with header)
            $result = @gzuncompress($stream);
            if ($result !== false) {
                return $result;
            }

            // Try zlib_decode
            if (function_exists('zlib_decode')) {
                $result = @zlib_decode($stream);
                if ($result !== false) {
                    return $result;
                }
            }

            // Try adding zlib header if missing
            $zlibStream = "\x78\x9c" . $stream;
            $result = @gzuncompress($zlibStream);
            if ($result !== false) {
                return $result;
            }
        }

        // Return raw stream if no decompression needed or possible
        return $stream;
    }

    /**
     * Extract readable text from a PDF stream content.
     *
     * @param string $stream Decompressed stream content
     * @return string Extracted text
     */
    private function extract_text_from_stream(string $stream): string {
        $text = '';

        // Extract text from BT...ET blocks
        if (preg_match_all('/BT\s*(.*?)\s*ET/s', $stream, $matches)) {
            foreach ($matches[1] as $textblock) {
                $extracted = $this->extract_text_from_block($textblock);
                if (!empty($extracted)) {
                    $text .= $extracted . ' ';
                }
            }
        }

        return $text;
    }

    /**
     * Format the extraction result.
     *
     * @param string $text Extracted text
     * @param array $methods Methods used for extraction
     * @return array Formatted result
     */
    private function format_result($text, array $methods = []) {
        // Clean and truncate text using text_helper
        $text = text_helper::clean_pdf_text($text);

        // Truncate to max length
        if (strlen($text) > $this->maxlength) {
            $text = text_helper::truncate($text, $this->maxlength);
        }

        if (empty($text)) {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error_no_text_extracted', 'local_pdfquizgen')
            ];
        }

        return [
            'success' => true,
            'text' => $text,
            'error' => '',
            'methods' => $methods
        ];
    }

    /**
     * Clean up extracted text.
     *
     * @param string $text The raw text
     * @return string Cleaned text
     * @deprecated Use text_helper::clean_pdf_text() instead
     */
    private function clean_text($text) {
        return text_helper::clean_pdf_text($text);
    }

    /**
     * Check if PDF extraction is available.
     *
     * @return array Array with 'available' bool and 'methods' array
     */
    public function check_availability() {
        $methods = [];

        // Check pdftotext
        if (PHP_OS_FAMILY === 'Windows') {
            exec('where pdftotext 2>nul', $output, $returnVar);
            if ($returnVar === 0) {
                $methods[] = 'pdftotext';
            }
        } else {
            $pdftotext = trim((string)shell_exec('which pdftotext 2>/dev/null'));
            if (!empty($pdftotext)) {
                $methods[] = 'pdftotext';
            }
        }

        // Check Smalot
        $parserpath = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($parserpath)) {
            $methods[] = 'smalot';
        }

        // Built-in methods are always available as fallback
        $methods[] = 'streams (built-in)';
        $methods[] = 'basic (fallback)';

        return [
            'available' => !empty($methods),
            'methods' => $methods
        ];
    }

    /**
     * Validate that extracted text is actual content, not PDF metadata.
     *
     * @param string $text Extracted text to validate
     * @return bool True if text appears to be valid content
     */
    private function is_valid_extracted_text($text): bool {
        if (empty($text)) {
            return false;
        }

        $text = trim($text);

        // Too short to be useful
        if (strlen($text) < 100) {
            return false;
        }

        // Check for PDF metadata patterns that indicate extraction failed
        $metadataPatterns = [
            '/^%PDF-[\d\.]+/i',                           // PDF version header
            '/\/Type\s*\/Page/i',                         // PDF page object
            '/\/MediaBox\s*\[/i',                         // PDF MediaBox
            '/\/Resources\s*<</i',                        // PDF Resources
            '/\/Filter\s*\/FlateDecode/i',                // PDF filter
            '/\/Length\s+\d+/i',                          // PDF stream length
            '/\/Font\s*<</i',                             // PDF font definition
            '/\/XObject\s*<</i',                          // PDF XObject
            '/endobj\s*\d+\s+\d+\s+obj/i',               // PDF object markers
            '/^\s*\d+\s+\d+\s+obj\s*$/m',                // PDF object definition
            '/stream\s*$/m',                              // PDF stream marker
            '/\/Producer\s*\(/i',                         // PDF metadata
            '/FlateDecode/i',                             // Filter name in text
            '/\/Subtype\s*\/Type1/i',                     // Font subtype
            '/\/BaseFont\s*\//i',                         // Base font definition
            '/\/Encoding\s*\//i',                         // Encoding definition
            '/xref\s+\d+\s+\d+/i',                       // PDF cross-reference
            '/trailer\s*<</i',                            // PDF trailer
            '/startxref/i',                               // PDF startxref
        ];

        $metadataCount = 0;
        foreach ($metadataPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $metadataCount++;
            }
        }

        // If more than 2 metadata patterns found, it's probably not valid text
        if ($metadataCount > 2) {
            return false;
        }

        // Check for binary garbage - high percentage of non-printable characters
        $nonPrintable = preg_match_all('/[^\x20-\x7E\x0A\x0D\xC0-\xFF]/', $text);
        $textLen = strlen($text);
        if ($textLen > 0 && ($nonPrintable / $textLen) > 0.1) {
            return false;
        }

        // Check if the text has reasonable word distribution
        $words = preg_split('/\s+/', $text);
        $wordCount = count($words);

        if ($wordCount < 20) {
            return false;
        }

        // Calculate average word length
        $totalLen = array_sum(array_map('strlen', $words));
        $avgWordLen = $totalLen / $wordCount;

        // Average word length should be between 2 and 12 characters
        if ($avgWordLen < 2 || $avgWordLen > 12) {
            return false;
        }

        return true;
    }
}
