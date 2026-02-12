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
 * Logs page for PDF Quiz Generator plugin.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/pdfquizgen/lib.php');

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/pdfquizgen:viewlogs', $context);

$PAGE->set_url(new moodle_url('/local/pdfquizgen/logs.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('viewlogs', 'local_pdfquizgen'));
$PAGE->set_heading(get_string('viewlogs', 'local_pdfquizgen'));
$PAGE->navbar->add(get_string('pluginname', 'local_pdfquizgen'), new moodle_url('/admin/settings.php', ['section' => 'local_pdfquizgen']));
$PAGE->navbar->add(get_string('viewlogs', 'local_pdfquizgen'));

// Build query
$params = [];
$where = [];

if ($courseid) {
    $where[] = 'l.courseid = :courseid';
    $params['courseid'] = $courseid;
}

$whereclause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countsql = "SELECT COUNT(*) FROM {local_pdfquizgen_logs} l $whereclause";
$totalcount = $DB->count_records_sql($countsql, $params);

// Get logs
$sql = "SELECT l.*, u.firstname, u.lastname, u.email, c.fullname as coursename
          FROM {local_pdfquizgen_logs} l
          JOIN {user} u ON l.userid = u.id
          LEFT JOIN {course} c ON l.courseid = c.id
       $whereclause
       ORDER BY l.timecreated DESC";

$logs = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

// Get courses for filter
$courses = $DB->get_records_sql(
    "SELECT DISTINCT c.id, c.fullname
       FROM {local_pdfquizgen_logs} l
       JOIN {course} c ON l.courseid = c.id
   ORDER BY c.fullname"
);

echo $OUTPUT->header();
?>

<div class="pdfquizgen-logs">
    <div class="mb-3">
        <form method="get" class="form-inline">
            <label for="courseid" class="mr-2"><?php echo get_string('filter_by_course', 'local_pdfquizgen'); ?>:</label>
            <select name="courseid" id="courseid" class="form-control mr-2">
                <option value="0"><?php echo get_string('all_courses', 'local_pdfquizgen'); ?></option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course->id; ?>" <?php echo $courseid == $course->id ? 'selected' : ''; ?>>
                        <?php echo s($course->fullname); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary"><?php echo get_string('filter', 'local_pdfquizgen'); ?></button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?php echo get_string('time', 'local_pdfquizgen'); ?></th>
                    <th><?php echo get_string('user', 'local_pdfquizgen'); ?></th>
                    <th><?php echo get_string('course', 'local_pdfquizgen'); ?></th>
                    <th><?php echo get_string('action', 'local_pdfquizgen'); ?></th>
                    <th><?php echo get_string('details', 'local_pdfquizgen'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">
                            <?php echo get_string('no_logs', 'local_pdfquizgen'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo userdate($log->timecreated, '%d/%m/%Y %H:%M:%S'); ?></td>
                            <td>
                                <a href="<?php echo new moodle_url('/user/profile.php', ['id' => $log->userid]); ?>">
                                    <?php echo fullname($log); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($log->courseid): ?>
                                    <a href="<?php echo new moodle_url('/course/view.php', ['id' => $log->courseid]); ?>">
                                        <?php echo s($log->coursename); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo s($log->action); ?></span>
                            </td>
                            <td>
                                <small><?php echo s($log->details); ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalcount > $perpage): ?>
        <?php echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $PAGE->url); ?>
    <?php endif; ?>

    <div class="mt-3">
        <a href="<?php echo new moodle_url('/admin/settings.php', ['section' => 'local_pdfquizgen']); ?>"
           class="btn btn-secondary">
            <?php echo get_string('back_to_settings', 'local_pdfquizgen'); ?>
        </a>
    </div>
</div>

<?php
echo $OUTPUT->footer();
