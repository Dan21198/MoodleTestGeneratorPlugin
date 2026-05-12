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
 * Factory for creating LLM clients.
 *
 * @package    local_quizgen
 * @copyright  2025 Daniel Horejski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizgen\llm;

defined('MOODLE_INTERNAL') || die();

/**
 * Factory for creating LLM clients.
 *
 * Handles instantiation of different LLM provider clients based on configuration.
 */
class llm_factory {

    /** @var string Default provider */
    const DEFAULT_PROVIDER = 'openrouter';

    /**
     * Create an LLM client based on configuration.
     *
     * @return llm_client_interface The LLM client
     * @throws \moodle_exception If provider not found or configured
     */
    public static function create() {
        $provider = get_config('local_quizgen', 'llm_provider') ?: self::DEFAULT_PROVIDER;
        $apikey = get_config('local_quizgen', 'llm_api_key');
        $model = self::get_configured_model();
        $maxtokens = (int)get_config('local_quizgen', 'max_tokens') ?: 2000;
        $timeout = (int)get_config('local_quizgen', 'llm_timeout') ?: 60;
        $maxretries = (int)get_config('local_quizgen', 'max_retries') ?: 3;

        if (empty($apikey)) {
            throw new \moodle_exception('error_api_not_configured', 'local_quizgen');
        }

        switch ($provider) {
            case 'openrouter':
                return new openrouter_client($apikey, $model, $maxtokens, $timeout, $maxretries);

            default:
                throw new \moodle_exception('error_invalid_provider', 'local_quizgen', '', $provider);
        }
    }

    /**
     * Get configured model based on provider and selection.
     *
     * @return string The model identifier
     */
    private static function get_configured_model() {
        $selectedmodel = get_config('local_quizgen', 'llm_model');

        // If custom model is selected, use custom model string
        if ($selectedmodel === 'custom') {
            $custommodel = get_config('local_quizgen', 'llm_model_custom');
            if (!empty($custommodel)) {
                return $custommodel;
            }
        }

        // Return selected model or default
        return $selectedmodel ?: 'openai/gpt-4o-mini';
    }

    /**
     * Get list of available providers.
     *
     * @return array Provider => name pairs
     */
    public static function get_providers() {
        return [
            'openrouter' => 'OpenRouter (multi-model gateway)',
        ];
    }

    /**
     * Get models for a specific provider.
     *
     * @param string $provider The provider name
     * @return array Model => display name pairs
     */
    public static function get_models_for_provider($provider) {
        switch ($provider) {
            case 'openrouter':
                return self::get_openrouter_models();

            default:
                return [];
        }
    }

    /**
     * Get OpenRouter models.
     *
     * @return array Models
     */
    private static function get_openrouter_models() {
        $customstring = get_string('llm_model_custom_label', 'local_quizgen');
        return [
            'openai/gpt-4o-mini' => 'GPT-4o Mini (OpenAI) - Fast & Affordable',
            'openai/gpt-4o' => 'GPT-4o (OpenAI) - Most Capable',
            'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet (Anthropic) - Excellent Quality',
            'anthropic/claude-3-haiku' => 'Claude 3 Haiku (Anthropic) - Fast & Cheap',
            'google/gemini-2.5-pro' => 'Gemini Pro 2.5 (Google) - Great for Long Content',
            'google/gemini-2.5-flash' => 'Gemini Flash 2.5 (Google) - Fast',
            'meta-llama/llama-3.1-70b-instruct' => 'Llama 3.1 70B (Meta) - Open Source',
            'mistralai/mistral-large-2512' => 'Mistral Large (Mistral AI) - European Alternative',
            'custom' => $customstring,
        ];
    }
}

