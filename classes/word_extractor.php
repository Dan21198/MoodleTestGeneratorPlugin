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
 * Word Document Text Extractor class.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen;

defined('MOODLE_INTERNAL') || die();

/**
 * Extracts text from Word documents (DOCX and DOC formats).
 */
class word_extractor {

    /** @var int Maximum file size in MB */
    private $maxsize;

    /** @var int Maximum text length to extract */
    private $maxlength;

    /** @var array Supported MIME types for Word documents */
    public const SUPPORTED_MIMETYPES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/msword', // .doc
    ];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->maxsize = get_config('local_pdfquizgen', 'max_pdf_size') ?: 50;
        $this->maxlength = get_config('local_pdfquizgen', 'max_text_length') ?: 15000;
    }

    /**
     * Check if a mimetype is supported by this extractor.
     *
     * @param string $mimetype The MIME type to check
     * @return bool True if supported
     */
    public static function is_supported_mimetype($mimetype) {
        return in_array($mimetype, self::SUPPORTED_MIMETYPES);
    }

    /**
     * Extract text from a stored_file object.
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

        // Check mimetype.
        if (!self::is_supported_mimetype($mimetype)) {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error_invalid_mimetype', 'local_pdfquizgen')
            ];
        }

        // Get file content.
        $content = $file->get_content();
        if (empty($content)) {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error_empty_file', 'local_pdfquizgen')
            ];
        }

        // Extract based on specific Word format.
        if ($mimetype === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return $this->extract_from_docx($content);
        } else {
            return $this->extract_from_doc($content);
        }
    }

    /**
     * Extract text from DOCX content.
     *
     * @param string $content DOCX file content
     * @return array Array with 'success', 'text', and 'error' keys
     */
    public function extract_from_docx($content) {
        try {
            // Create a temporary file.
            $tempfile = tempnam(sys_get_temp_dir(), 'docx_');
            file_put_contents($tempfile, $content);

            $text = $this->read_docx($tempfile);

            // Clean up.
            @unlink($tempfile);

            if (empty($text)) {
                return [
                    'success' => false,
                    'text' => '',
                    'error' => get_string('error_no_text_extracted', 'local_pdfquizgen')
                ];
            }

            return $this->format_result($text, 'docx');

        } catch (\Exception $e) {
            return [
                'success' => false,
                'text' => '',
                'error' => 'DOCX extraction error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extract text from DOC content (legacy Word format).
     *
     * @param string $content DOC file content
     * @return array Array with 'success', 'text', and 'error' keys
     */
    public function extract_from_doc($content) {
        try {
            $text = $this->read_doc($content);

            if (empty($text)) {
                return [
                    'success' => false,
                    'text' => '',
                    'error' => get_string('error_no_text_extracted', 'local_pdfquizgen')
                ];
            }

            return $this->format_result($text, 'doc');

        } catch (\Exception $e) {
            return [
                'success' => false,
                'text' => '',
                'error' => 'DOC extraction error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Read text from a DOCX file.
     *
     * @param string $filepath Path to the DOCX file
     * @return string Extracted text
     */
    private function read_docx($filepath) {
        $text = '';

        // DOCX is a ZIP archive.
        $zip = new \ZipArchive();
        if ($zip->open($filepath) === true) {
            // Read the main document content.
            $xmlContent = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($xmlContent !== false) {
                // Parse XML and extract text.
                $text = $this->extract_text_from_docx_xml($xmlContent);
            }
        }

        return $text;
    }

    /**
     * Extract text from DOCX XML content.
     *
     * @param string $xmlContent The XML content from document.xml
     * @return string Extracted text
     */
    private function extract_text_from_docx_xml($xmlContent) {
        // Disable external entity loading for security.
        $previousValue = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadXML($xmlContent, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $textNodes = [];
        $paragraphs = $xpath->query('//w:p');

        foreach ($paragraphs as $paragraph) {
            $paragraphText = [];
            $textElements = $xpath->query('.//w:t', $paragraph);

            foreach ($textElements as $textElement) {
                $paragraphText[] = $textElement->textContent;
            }

            if (!empty($paragraphText)) {
                $textNodes[] = implode('', $paragraphText);
            }
        }

        libxml_use_internal_errors($previousValue);

        return implode("\n", $textNodes);
    }

    /**
     * Read text from a DOC file (legacy binary format).
     *
     * @param string $content Binary content of the DOC file
     * @return string Extracted text
     */
    private function read_doc($content) {
        $text = '';

        // Method 1: Look for ASCII text between specific markers.
        if (preg_match_all('/[\x20-\x7E\xA0-\xFF]{4,}/', $content, $matches)) {
            $text = implode(' ', $matches[0]);
        }

        // Method 2: Try to find text in specific DOC structures.
        $cleanText = $this->extract_doc_text_advanced($content);
        if (!empty($cleanText) && strlen($cleanText) > strlen($text)) {
            $text = $cleanText;
        }

        return $this->clean_text($text);
    }

    /**
     * Advanced DOC text extraction.
     *
     * @param string $content Binary DOC content
     * @return string Extracted text
     */
    private function extract_doc_text_advanced($content) {
        $lines = [];

        $length = strlen($content);
        $currentWord = '';

        for ($i = 0; $i < $length; $i++) {
            $char = ord($content[$i]);

            if (($char >= 32 && $char <= 126) || ($char >= 160 && $char <= 255)) {
                $currentWord .= chr($char);
            } else if ($char === 13 || $char === 10) {
                if (strlen($currentWord) >= 3) {
                    $lines[] = trim($currentWord);
                }
                $currentWord = '';
            } else {
                if (strlen($currentWord) >= 3) {
                    $lines[] = trim($currentWord);
                }
                $currentWord = '';
            }
        }

        if (strlen($currentWord) >= 3) {
            $lines[] = trim($currentWord);
        }

        // Filter out obviously non-text content.
        $filteredLines = [];
        foreach ($lines as $line) {
            if (preg_match('/^[\w\s.,;:!?\'"()\-]+$/u', $line) && strlen($line) > 5) {
                $filteredLines[] = $line;
            }
        }

        return implode("\n", $filteredLines);
    }

    /**
     * Format extraction result.
     *
     * @param string $text The extracted text
     * @param string $method The extraction method used
     * @return array Result array
     */
    private function format_result($text, $method) {
        $text = $this->clean_text($text);

        // Truncate if too long.
        if (strlen($text) > $this->maxlength) {
            $text = substr($text, 0, $this->maxlength);
            // Try to end at a sentence.
            $lastPeriod = strrpos($text, '.');
            if ($lastPeriod > $this->maxlength - 500) {
                $text = substr($text, 0, $lastPeriod + 1);
            }
        }

        if (empty(trim($text))) {
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
            'method' => $method
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

        // Try to detect and convert encoding to UTF-8.
        $encoding = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'ASCII'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $converted = @mb_convert_encoding($text, 'UTF-8', $encoding);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        // Remove null bytes.
        $text = str_replace("\0", '', $text);

        // Remove invalid UTF-8 sequences.
        $text = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        if ($text === false) {
            $text = '';
        }

        // Remove BOM if present.
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);

        // Normalize line endings.
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove excessive whitespace.
        $text = preg_replace('/[^\S\n]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Remove control characters except newline and tab.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        return trim($text);
    }
}

