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
 * Admin settings for MoodleTestGeneratorPlugin plugin.
 *
 * @package    local_quizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_quizgen', get_string('pluginname', 'local_quizgen'));

    // OpenRouter API Settings
    $settings->add(new admin_setting_heading(
        'local_quizgen/openrouter_heading',
        get_string('openrouter_settings', 'local_quizgen'),
        get_string('openrouter_settings_desc', 'local_quizgen')
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizgen/openrouter_api_key',
        get_string('openrouter_api_key', 'local_quizgen'),
        get_string('openrouter_api_key_desc', 'local_quizgen'),
        '',
        PARAM_TEXT,
        64
    ));

    // Build model choices - avoid get_string in array definition to prevent cache issues
    $otherstring = get_string('openrouter_model_other', 'local_quizgen');
    $popularmodels = [
        'openai/gpt-4o-mini' => 'GPT-4o Mini (OpenAI) - Fast & Affordable',
        'openai/gpt-4o' => 'GPT-4o (OpenAI) - Most Capable',
        'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet (Anthropic) - Excellent Quality',
        'anthropic/claude-3-haiku' => 'Claude 3 Haiku (Anthropic) - Fast & Cheap',
        'google/gemini-2.5-pro' => 'Gemini Pro 2.5 (Google) - Great for Long Content',
        'google/gemini-2.5-flash' => 'Gemini Flash 2.5 (Google) - Fast',
        'meta-llama/llama-3.1-70b-instruct' => 'Llama 3.1 70B (Meta) - Open Source',
        'mistralai/mistral-large-2512' => 'Mistral Large (Mistral AI) - European Alternative',
        'other' => $otherstring,
    ];

    $settings->add(new admin_setting_configselect(
        'local_quizgen/openrouter_model',
        get_string('openrouter_model', 'local_quizgen'),
        get_string('openrouter_model_desc', 'local_quizgen'),
        'openai/gpt-4o-mini',
        $popularmodels
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizgen/openrouter_model_custom',
        get_string('openrouter_model_custom', 'local_quizgen'),
        get_string('openrouter_model_custom_desc', 'local_quizgen'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizgen/openrouter_timeout',
        get_string('openrouter_timeout', 'local_quizgen'),
        get_string('openrouter_timeout_desc', 'local_quizgen'),
        '60',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizgen/max_tokens',
        get_string('max_tokens', 'local_quizgen'),
        get_string('max_tokens_desc', 'local_quizgen'),
        '2000',
        PARAM_INT
    ));

    // Default Quiz Settings
    $settings->add(new admin_setting_heading(
        'local_quizgen/quiz_defaults_heading',
        get_string('quiz_defaults', 'local_quizgen'),
        get_string('quiz_defaults_desc', 'local_quizgen')
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizgen/default_question_count',
        get_string('default_question_count', 'local_quizgen'),
        get_string('default_question_count_desc', 'local_quizgen'),
        '10',
        PARAM_INT
    ));

    $questiontypes = [
        'multichoice' => get_string('multichoice', 'local_quizgen'),
        'truefalse' => get_string('truefalse', 'local_quizgen'),
        'shortanswer' => get_string('shortanswer', 'local_quizgen'),
        'mixed' => get_string('mixed', 'local_quizgen'),
    ];

    $settings->add(new admin_setting_configselect(
        'local_quizgen/default_question_type',
        get_string('default_question_type', 'local_quizgen'),
        get_string('default_question_type_desc', 'local_quizgen'),
        'multichoice',
        $questiontypes
    ));

    // PDF Processing Settings
    $settings->add(new admin_setting_heading(
        'local_quizgen/pdf_settings_heading',
        get_string('pdf_settings', 'local_quizgen'),
        get_string('pdf_settings_desc', 'local_quizgen')
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizgen/max_pdf_size',
        get_string('max_pdf_size', 'local_quizgen'),
        get_string('max_pdf_size_desc', 'local_quizgen'),
        '50',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizgen/max_text_length',
        get_string('max_text_length', 'local_quizgen'),
        get_string('max_text_length_desc', 'local_quizgen'),
        '15000',
        PARAM_INT
    ));

    // Advanced Settings
    $settings->add(new admin_setting_heading(
        'local_quizgen/advanced_heading',
        get_string('advanced_settings', 'local_quizgen'),
        get_string('advanced_settings_desc', 'local_quizgen')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_quizgen/enable_logging',
        get_string('enable_logging', 'local_quizgen'),
        get_string('enable_logging_desc', 'local_quizgen'),
        '1'
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizgen/max_retries',
        get_string('max_retries', 'local_quizgen'),
        get_string('max_retries_desc', 'local_quizgen'),
        '3',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_quizgen_logs',
        get_string('viewlogs', 'local_quizgen'),
        new moodle_url('/local/quizgen/logs.php'),
        'moodle/site:config'
    ));
}