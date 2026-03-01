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
 * CLI script to test OpenRouter API connection.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Get CLI options
list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'test-generation' => false,
    ],
    [
        'h' => 'help',
        't' => 'test-generation',
    ]
);

if ($options['help']) {
    echo "Test OpenRouter API connection for MoodleTestGeneratorPlugin.

Options:
    -h, --help          Show this help
    -t, --test-generation  Test actual question generation

Examples:
    php test_api.php
    php test_api.php --test-generation
";
    exit(0);
}

echo "MoodleTestGeneratorPlugin - API Test\n";
echo "==============================\n\n";

// Check if plugin is configured
$apikey = get_config('local_pdfquizgen', 'openrouter_api_key');
$model = get_config('local_pdfquizgen', 'openrouter_model');

if (empty($apikey)) {
    cli_error("ERROR: OpenRouter API key is not configured!\n" .
              "Please configure it at: Site Administration > Plugins > Local Plugins > MoodleTestGeneratorPlugin");
}

echo "API Key: " . substr($apikey, 0, 10) . "..." . substr($apikey, -4) . "\n";
echo "Model: $model\n\n";

// Test connection
echo "Testing API connection...\n";

try {
    $client = new \local_pdfquizgen\openrouter_client();
    $result = $client->test_connection();

    if ($result['success']) {
        echo "✓ API connection successful!\n\n";
    } else {
        cli_error("✗ API connection failed: " . $result['error']);
    }
} catch (Exception $e) {
    cli_error("✗ Error: " . $e->getMessage());
}

// Test question generation if requested
if ($options['test-generation']) {
    echo "Testing question generation...\n";

    $sampletext = "The water cycle, also known as the hydrologic cycle, describes the continuous movement of water on, above, and below the surface of the Earth. Water changes states between liquid, vapor, and ice at various places in the water cycle. The major processes involved are evaporation, condensation, precipitation, infiltration, runoff, and subsurface flow.";

    echo "Sample text: \"" . substr($sampletext, 0, 100) . "...\"\n\n";

    try {
        $result = $client->generate_questions($sampletext, 3, 'multichoice');

        if ($result['success']) {
            echo "✓ Question generation successful!\n";
            echo "Generated " . count($result['questions']) . " questions:\n\n";

            foreach ($result['questions'] as $i => $q) {
                echo "Question " . ($i + 1) . ":\n";
                echo "  Type: " . $q['type'] . "\n";
                echo "  Q: " . $q['question'] . "\n";
                if (isset($q['options'])) {
                    echo "  Options:\n";
                    foreach ($q['options'] as $j => $opt) {
                        $marker = ($j == $q['correct_answer']) ? ' ✓' : '';
                        echo "    " . chr(97 + $j) . ") $opt$marker\n";
                    }
                }
                echo "  Explanation: " . ($q['explanation'] ?? 'N/A') . "\n\n";
            }
        } else {
            cli_error("✗ Question generation failed: " . $result['error']);
        }
    } catch (Exception $e) {
        cli_error("✗ Error: " . $e->getMessage());
    }
}

echo "\nTest completed successfully!\n";
exit(0);
