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
 * Library functions for PDF Quiz Generator plugin.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/pdfquizgen/classes/pdf_extractor.php');
require_once($CFG->dirroot . '/local/pdfquizgen/classes/openrouter_client.php');
require_once($CFG->dirroot . '/local/pdfquizgen/classes/quiz_generator.php');
require_once($CFG->dirroot . '/local/pdfquizgen/classes/job_manager.php');

/**
 * Get the course files of type PDF.
 *
 * @param int $courseid The course ID
 * @return array Array of file records
 */
function local_pdfquizgen_get_course_pdf_files($courseid) {
    global $DB;

    $context = context_course::instance($courseid);

    $sql = "SELECT f.*, r.name as resource_name, r.intro as resource_intro
              FROM {files} f
              JOIN {context} ctx ON f.contextid = ctx.id
              LEFT JOIN {course_modules} cm ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
              LEFT JOIN {resource} r ON cm.instance = r.id
             WHERE (ctx.id = :coursecontext OR ctx.path LIKE :coursepath)
               AND f.mimetype = :mimetype
               AND f.filesize > 0
               AND f.filename != '.'
             ORDER BY f.timecreated DESC";

    $params = [
        'coursecontext' => $context->id,
        'coursepath' => $context->path . '/%',
        'modlevel' => CONTEXT_MODULE,
        'mimetype' => 'application/pdf'
    ];

    return $DB->get_records_sql($sql, $params);
}

/**
 * Log an action in the plugin logs.
 *
 * @param int $courseid The course ID
 * @param int $userid The user ID
 * @param string $action The action performed
 * @param int|null $jobid The job ID if applicable
 * @param string|null $details Additional details
 * @return bool True on success
 */
function local_pdfquizgen_log($courseid, $userid, $action, $jobid = null, $details = null) {
    global $DB;

    if (!get_config('local_pdfquizgen', 'enable_logging')) {
        return true;
    }

    $log = new stdClass();
    $log->jobid = $jobid;
    $log->courseid = $courseid;
    $log->userid = $userid;
    $log->action = $action;
    $log->details = $details;
    $log->timecreated = time();

    return $DB->insert_record('local_pdfquizgen_logs', $log);
}

/**
 * Check if the plugin is properly configured.
 *
 * @return bool True if configured
 */
function local_pdfquizgen_is_configured() {
    $apikey = get_config('local_pdfquizgen', 'openrouter_api_key');
    return !empty($apikey);
}

/**
 * Get status text for display.
 *
 * @param string $status The status code
 * @return string Human-readable status
 */
function local_pdfquizgen_get_status_text($status) {
    $statuses = [
        'pending' => get_string('status_pending', 'local_pdfquizgen'),
        'processing' => get_string('status_processing', 'local_pdfquizgen'),
        'completed' => get_string('status_completed', 'local_pdfquizgen'),
        'failed' => get_string('status_failed', 'local_pdfquizgen'),
    ];

    return $statuses[$status] ?? $status;
}

/**
 * Get status CSS class for styling.
 *
 * @param string $status The status code
 * @return string CSS class name
 */
function local_pdfquizgen_get_status_class($status) {
    $classes = [
        'pending' => 'badge badge-warning',
        'processing' => 'badge badge-info',
        'completed' => 'badge badge-success',
        'failed' => 'badge badge-danger',
    ];

    return $classes[$status] ?? 'badge badge-secondary';
}

/**
 * Clean up old jobs and logs.
 *
 * @param int $olderthan Delete records older than this many days
 * @return bool True on success
 */
function local_pdfquizgen_cleanup_old_data($olderthan = 30) {
    global $DB;

    $cutoff = time() - ($olderthan * DAYSECS);

    // Delete old logs
    $DB->delete_records_select('local_pdfquizgen_logs', 'timecreated < :cutoff', ['cutoff' => $cutoff]);

    // Delete old completed/failed jobs
    $DB->delete_records_select(
        'local_pdfquizgen_jobs',
        'timecreated < :cutoff AND status IN ("completed", "failed")',
        ['cutoff' => $cutoff]
    );

    return true;
}

/**
 * Extend navigation to add link to course navigation.
 * Note: If you use this AND extend_settings_navigation, you might get duplicates
 * in some themes. I have added a check here to be safe.
 *
 * @param navigation_node $navigation The navigation node
 * @param stdClass $course The course object
 * @param context $context The course context
 */
function local_pdfquizgen_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('local/pdfquizgen:use', $context)) {

        // FIX: Check if node exists to prevent "Adding a node that already exists" error
        if (!$navigation->get('pdfquizgen')) {
            $url = new moodle_url('/local/pdfquizgen/index.php', ['courseid' => $course->id]);
            $navigation->add(
                get_string('pluginname', 'local_pdfquizgen'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'pdfquizgen',
                new pix_icon('i/edit', '')
            );
        }
    }
}

/**
 * Extend settings navigation (Course Administration menu).
 *
 * @param settings_navigation $nav The settings navigation
 * @param context $context The current context
 */
function local_pdfquizgen_extend_settings_navigation($nav, $context) {
    if ($context->contextlevel == CONTEXT_COURSE) {
        if (has_capability('local/pdfquizgen:use', $context)) {
            $courseadmin = $nav->get('courseadmin');

            if ($courseadmin) {
                if (!$courseadmin->get('pdfquizgen')) {
                    $url = new moodle_url('/local/pdfquizgen/index.php', ['courseid' => $context->instanceid]);
                    $courseadmin->add(
                        get_string('pluginname', 'local_pdfquizgen'),
                        $url,
                        navigation_node::TYPE_SETTING,
                        null,
                        'pdfquizgen',
                        new pix_icon('i/edit', '')
                    );
                }
            }
        }
    }
}