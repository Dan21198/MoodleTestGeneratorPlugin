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
 * Admin settings for PDF Quiz Generator plugin.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_pdfquizgen', get_string('pluginname', 'local_pdfquizgen'));

    // OpenRouter API Settings
    $settings->add(new admin_setting_heading(
        'local_pdfquizgen/openrouter_heading',
        get_string('openrouter_settings', 'local_pdfquizgen'),
        get_string('openrouter_settings_desc', 'local_pdfquizgen')
    ));

    $settings->add(new admin_setting_configtext(
        'local_pdfquizgen/openrouter_api_key',
        get_string('openrouter_api_key', 'local_pdfquizgen'),
        get_string('openrouter_api_key_desc', 'local_pdfquizgen'),
        '',
        PARAM_TEXT,
        64
    ));

    $settings->add(new admin_setting_configtext(
        'local_pdfquizgen/openrouter_model',
        get_string('openrouter_model', 'local_pdfquizgen'),
        get_string('openrouter_model_desc', 'local_pdfquizgen'),
        'openai/gpt-4o-mini',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_pdfquizgen/openrouter_timeout',
        get_string('openrouter_timeout', 'local_pdfquizgen'),
        get_string('openrouter_timeout_desc', 'local_pdfquizgen'),
        '60',
        PARAM_INT
    ));

    // Default Quiz Settings
    $settings->add(new admin_setting_heading(
        'local_pdfquizgen/quiz_defaults_heading',
        get_string('quiz_defaults', 'local_pdfquizgen'),
        get_string('quiz_defaults_desc', 'local_pdfquizgen')
    ));

    $settings->add(new admin_setting_configtext(
        'local_pdfquizgen/default_question_count',
        get_string('default_question_count', 'local_pdfquizgen'),
        get_string('default_question_count_desc', 'local_pdfquizgen'),
        '10',
        PARAM_INT
    ));

    $questiontypes = [
        'multichoice' => get_string('multichoice', 'local_pdfquizgen'),
        'truefalse' => get_string('truefalse', 'local_pdfquizgen'),
        'shortanswer' => get_string('shortanswer', 'local_pdfquizgen'),
        'mixed' => get_string('mixed', 'local_pdfquizgen'),
    ];

    $settings->add(new admin_setting_configselect(
        'local_pdfquizgen/default_question_type',
        get_string('default_question_type', 'local_pdfquizgen'),
        get_string('default_question_type_desc', 'local_pdfquizgen'),
        'multichoice',
        $questiontypes
    ));

    // PDF Processing Settings
    $settings->add(new admin_setting_heading(
        'local_pdfquizgen/pdf_settings_heading',
        get_string('pdf_settings', 'local_pdfquizgen'),
        get_string('pdf_settings_desc', 'local_pdfquizgen')
    ));

    $settings->add(new admin_setting_configtext(
        'local_pdfquizgen/max_pdf_size',
        get_string('max_pdf_size', 'local_pdfquizgen'),
        get_string('max_pdf_size_desc', 'local_pdfquizgen'),
        '50',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_pdfquizgen/max_text_length',
        get_string('max_text_length', 'local_pdfquizgen'),
        get_string('max_text_length_desc', 'local_pdfquizgen'),
        '15000',
        PARAM_INT
    ));

    // Advanced Settings
    $settings->add(new admin_setting_heading(
        'local_pdfquizgen/advanced_heading',
        get_string('advanced_settings', 'local_pdfquizgen'),
        get_string('advanced_settings_desc', 'local_pdfquizgen')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_pdfquizgen/enable_logging',
        get_string('enable_logging', 'local_pdfquizgen'),
        get_string('enable_logging_desc', 'local_pdfquizgen'),
        '1'
    ));

    $settings->add(new admin_setting_configtext(
        'local_pdfquizgen/max_retries',
        get_string('max_retries', 'local_pdfquizgen'),
        get_string('max_retries_desc', 'local_pdfquizgen'),
        '3',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_pdfquizgen_logs',
        get_string('viewlogs', 'local_pdfquizgen'),
        new moodle_url('/local/pdfquizgen/logs.php'),
        'moodle/site:config'
    ));
}