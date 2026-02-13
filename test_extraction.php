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
 * Test PDF extraction.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_pdfquizgen_settings');
require_login();
require_capability('moodle/site:config', context_system::instance());

use local_pdfquizgen\pdf_extractor;

$PAGE->set_url(new moodle_url('/local/pdfquizgen/test_extraction.php'));
$PAGE->set_title('Test PDF Extraction');
$PAGE->set_heading('Test PDF Extraction');

echo $OUTPUT->header();

echo '<h2>PDF Extraction Test</h2>';

// Check available extraction methods
$extractor = new pdf_extractor();
$availability = $extractor->check_availability();

echo '<h3>Available Extraction Methods</h3>';
echo '<ul>';
foreach ($availability['methods'] as $method) {
    echo "<li><strong>{$method}:</strong> <span style='color:green'>✓ Available</span></li>";
}
echo '</ul>';

// Test with a specific file ID if provided
$fileid = optional_param('fileid', 0, PARAM_INT);

if ($fileid) {
    echo '<h3>Testing File ID: ' . $fileid . '</h3>';

    $result = $extractor->extract_from_fileid($fileid);

    echo '<pre>';
    echo 'Success: ' . ($result['success'] ? 'Yes' : 'No') . "\n";
    echo 'Error: ' . ($result['error'] ?: 'None') . "\n";
    echo 'Methods used: ' . (isset($result['methods']) ? implode(', ', $result['methods']) : 'N/A') . "\n";
    echo "\nExtracted text (" . strlen($result['text']) . " chars):\n";
    echo "---\n";
    echo htmlspecialchars(substr($result['text'], 0, 2000));
    if (strlen($result['text']) > 2000) {
        echo "\n... (truncated)";
    }
    echo "\n---\n";
    echo '</pre>';
}

// List PDF files in the system
echo '<h3>Test a PDF File</h3>';

$fs = get_file_storage();
$files = $DB->get_records_sql("
    SELECT f.id, f.filename, f.contextid, f.filesize, f.timecreated
    FROM {files} f
    WHERE f.mimetype = 'application/pdf'
    AND f.filesize > 0
    ORDER BY f.timecreated DESC
    LIMIT 20
");

if ($files) {
    echo '<table class="table">';
    echo '<tr><th>ID</th><th>Filename</th><th>Size</th><th>Actions</th></tr>';
    foreach ($files as $file) {
        $size = display_size($file->filesize);
        $testurl = new moodle_url('/local/pdfquizgen/test_extraction.php', ['fileid' => $file->id]);
        echo "<tr>";
        echo "<td>{$file->id}</td>";
        echo "<td>" . htmlspecialchars($file->filename) . "</td>";
        echo "<td>{$size}</td>";
        echo "<td><a href='{$testurl}' class='btn btn-sm btn-primary'>Test Extract</a></td>";
        echo "</tr>";
    }
    echo '</table>';
} else {
    echo '<p>No PDF files found in the system.</p>';
}

echo $OUTPUT->footer();

