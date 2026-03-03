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
 * External services for MoodleTestGeneratorPlugin.
 *
 * @package    local_quizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_quizgen_process_job' => [
        'classname'     => 'local_quizgen\external\process_job',
        'description'   => 'Process a quiz generation job',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/quizgen:use',
        'services'      => [],
    ],
    'local_quizgen_get_job_status' => [
        'classname'     => 'local_quizgen\external\get_job_status',
        'description'   => 'Get the status of a quiz generation job',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/quizgen:use',
        'services'      => [],
    ],
];

