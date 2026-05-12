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
 * Test script for verifying LLM client refactoring.
 *
 * Usage: php local/quizgen/cli/test_llm_factory.php
 *
 * @package    local_quizgen
 * @copyright  2025 Daniel Horejski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_quizgen\llm\llm_factory;

// Now run the tests.
cli_heading('LLM Client Refactoring Tests');

// Test 1: Factory loads
cli_writeln('');
cli_writeln('Test 1: LLM Factory can be loaded');
try {
    $class = '\local_quizgen\llm\llm_factory';
    if (class_exists($class)) {
        cli_writeln('✓ llm_factory class found', true);
    } else {
        cli_writeln('✗ llm_factory class NOT found', false);
        exit(1);
    }
} catch (Exception $e) {
    cli_writeln('✗ Error loading factory: ' . $e->getMessage(), false);
    exit(1);
}

// Test 2: Get available providers
cli_writeln('');
cli_writeln('Test 2: Get available providers');
try {
    $providers = llm_factory::get_providers();
    if (!empty($providers)) {
        cli_writeln('✓ Found ' . count($providers) . ' provider(s):', true);
        foreach ($providers as $key => $name) {
            cli_writeln('  - ' . $key . ': ' . $name);
        }
    } else {
        cli_writeln('✗ No providers found', false);
        exit(1);
    }
} catch (Exception $e) {
    cli_writeln('✗ Error getting providers: ' . $e->getMessage(), false);
    exit(1);
}

// Test 3: Get models for provider
cli_writeln('');
cli_writeln('Test 3: Get models for OpenRouter provider');
try {
    $models = llm_factory::get_models_for_provider('openrouter');
    if (!empty($models)) {
        cli_writeln('✓ Found ' . count($models) . ' model(s) for OpenRouter:', true);
        foreach ($models as $key => $name) {
            cli_writeln('  - ' . $key . ': ' . substr($name, 0, 50) . '...');
        }
    } else {
        cli_writeln('✗ No models found for OpenRouter', false);
        exit(1);
    }
} catch (Exception $e) {
    cli_writeln('✗ Error getting models: ' . $e->getMessage(), false);
    exit(1);
}

// Test 4: Check configuration
cli_writeln('');
cli_writeln('Test 4: Check LLM configuration');
$provider = get_config('local_quizgen', 'llm_provider');
$apikey = get_config('local_quizgen', 'llm_api_key');
$model = get_config('local_quizgen', 'llm_model');
$timeout = get_config('local_quizgen', 'llm_timeout');
$maxtokens = get_config('local_quizgen', 'max_tokens');

if (!empty($provider)) {
    cli_writeln('✓ LLM Provider set: ' . $provider, true);
} else {
    cli_writeln('⚠ LLM Provider not set, using default: openrouter', false);
}

if (!empty($apikey)) {
    cli_writeln('✓ API Key configured (length: ' . strlen($apikey) . ')', true);
} else {
    cli_writeln('✗ API Key NOT configured - cannot test connection', false);
}

if (!empty($model)) {
    cli_writeln('✓ Model set: ' . $model, true);
} else {
    cli_writeln('⚠ Model not set, using default', false);
}

cli_writeln('  Timeout: ' . ($timeout ?: 'default (60s)'));
cli_writeln('  Max Tokens: ' . ($maxtokens ?: 'default (2000)'));

// Test 5: Try to create client
cli_writeln('');
cli_writeln('Test 5: Try to create LLM client');
if (empty($apikey)) {
    cli_writeln('⚠ Skipped - API Key not configured', false);
} else {
    try {
        $client = llm_factory::create();
        $info = $client->get_info();
        cli_writeln('✓ Client created successfully:', true);
        cli_writeln('  Provider: ' . $info['name']);
        cli_writeln('  Model: ' . $info['model']);
        cli_writeln('  Endpoint: ' . ($info['endpoint'] ?? 'N/A'));
    } catch (Exception $e) {
        cli_writeln('✗ Error creating client: ' . $e->getMessage(), false);
    }
}

// Test 6: Check backward compatibility
cli_writeln('');
cli_writeln('Test 6: Backward compatibility - old openrouter_client class');
try {
    $class = '\local_quizgen\openrouter_client';
    if (class_exists($class)) {
        cli_writeln('✓ Old openrouter_client class still exists', true);
        cli_writeln('  This is deprecated but kept for backward compatibility');
    } else {
        cli_writeln('✗ Old openrouter_client class not found', false);
    }
} catch (Exception $e) {
    cli_writeln('✗ Error checking backward compatibility: ' . $e->getMessage(), false);
}

// Test 7: Test connection (if API key is set)
cli_writeln('');
cli_writeln('Test 7: Test API connection');
if (empty($apikey)) {
    cli_writeln('⚠ Skipped - API Key not configured', false);
} else {
    try {
        $client = llm_factory::create();
        cli_writeln('Testing connection to ' . $client->get_info()['name'] . '...');
        $result = $client->test_connection();
        
        if ($result['success']) {
            cli_writeln('✓ Connection test PASSED', true);
        } else {
            cli_writeln('✗ Connection test FAILED: ' . $result['error'], false);
        }
    } catch (Exception $e) {
        cli_writeln('✗ Error testing connection: ' . $e->getMessage(), false);
    }
}

// Summary
cli_writeln('');
cli_heading('Test Summary');
cli_writeln('LLM Client refactoring tests completed.');
cli_writeln('');
cli_writeln('Configuration next steps:');
cli_writeln('1. Go to Site Administration > Local Plugins > MoodleTestGeneratorPlugin');
cli_writeln('2. Set LLM Provider (default: OpenRouter)');
cli_writeln('3. Enter your API Key');
cli_writeln('4. Select or specify a model');
cli_writeln('5. Run tests to verify');
cli_writeln('');
cli_writeln('For more information, see:');
cli_writeln('- LLM_ARCHITECTURE.md - Detailed architecture documentation');
cli_writeln('- MIGRATION_GUIDE.md - Migration guide from old system');
cli_writeln('');

