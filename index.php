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
 * Main page for PDF Quiz Generator plugin.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/pdfquizgen/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$jobid = optional_param('jobid', 0, PARAM_INT);
$fileid = optional_param('fileid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/pdfquizgen:use', $context);

if (!local_pdfquizgen_is_configured()) {
    redirect(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('error_not_configured', 'local_pdfquizgen'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$PAGE->set_url(new moodle_url('/local/pdfquizgen/index.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_pdfquizgen'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('pluginname', 'local_pdfquizgen'));

$jobmanager = new \local_pdfquizgen\job_manager($courseid, $USER->id);
$message = '';
$messagetype = '';

// Handle message from redirect (PRG pattern)
$msg = optional_param('msg', '', PARAM_ALPHA);
$filecount = optional_param('filecount', 1, PARAM_INT);
switch ($msg) {
    case 'queued':
        if ($filecount > 1) {
            $message = get_string('job_queued_multi', 'local_pdfquizgen', $filecount);
        } else {
            $message = get_string('job_queued', 'local_pdfquizgen');
        }
        $messagetype = 'info';
        break;
    case 'deleted':
        $message = get_string('job_deleted_success', 'local_pdfquizgen');
        $messagetype = 'success';
        break;
    case 'delete_error':
        $message = get_string('job_deleted_error', 'local_pdfquizgen');
        $messagetype = 'error';
        break;
    case 'retried':
        $message = get_string('job_retried_success', 'local_pdfquizgen');
        $messagetype = 'success';
        break;
    case 'retry_error':
        $message = get_string('job_retried_error', 'local_pdfquizgen');
        $messagetype = 'error';
        break;
}

// Handle actions
if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'create':
            $questioncount = optional_param('questioncount', 10, PARAM_INT);
            $questiontype = optional_param('questiontype', 'multichoice', PARAM_ALPHA);
            $fileids = optional_param_array('fileids', [], PARAM_INT);

            $questioncount = max(1, min(100, $questioncount));

            if (!empty($fileids)) {
                // Collect file information for all selected files
                $filenames = [];
                $validfileids = [];
                foreach ($fileids as $fid) {
                    $file = $DB->get_record('files', ['id' => $fid]);
                    if ($file) {
                        $validfileids[] = $fid;
                        $filenames[] = $file->filename;
                    }
                }

                if (!empty($validfileids)) {
                    // Create a single job with multiple files
                    $combinedfilename = count($filenames) > 1
                        ? count($filenames) . ' files (' . implode(', ', array_slice($filenames, 0, 3)) . (count($filenames) > 3 ? '...' : '') . ')'
                        : $filenames[0];

                    $jobid = $jobmanager->create_job_multi($validfileids, $combinedfilename, $questioncount, $questiontype);

                    // Redirect to prevent duplicate submission on refresh (PRG pattern)
                    $redirecturl = new moodle_url('/local/pdfquizgen/index.php', [
                        'courseid' => $courseid,
                        'msg' => 'queued',
                        'filecount' => count($validfileids)
                    ]);
                    redirect($redirecturl);
                }
            }
            break;

        case 'delete':
            if ($jobid) {
                $success = $jobmanager->delete_job($jobid);
                $redirecturl = new moodle_url('/local/pdfquizgen/index.php', [
                    'courseid' => $courseid,
                    'msg' => $success ? 'deleted' : 'delete_error'
                ]);
                redirect($redirecturl);
            }
            break;

        case 'retry':
            if ($jobid) {
                $result = $jobmanager->retry_job($jobid);
                $redirecturl = new moodle_url('/local/pdfquizgen/index.php', [
                    'courseid' => $courseid,
                    'msg' => $result['success'] ? 'retried' : 'retry_error'
                ]);
                redirect($redirecturl);
            }
            break;
    }
}

// Get data for display
$pdffiles = local_pdfquizgen_get_course_pdf_files($courseid);
$jobs = $jobmanager->get_course_jobs(20);
$stats = $jobmanager->get_statistics();

// Output header
echo $OUTPUT->header();

// Display messages
if ($message) {
    echo $OUTPUT->notification($message, $messagetype);
}

// Display statistics
?>
<div class="pdfquizgen-dashboard">
    <div class="stats-cards mb-4">
        <div class="row">
            <div class="col-md-2 col-sm-4 col-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="mb-0" data-stat="total"><?php echo $stats['total']; ?></h3>
                        <small class="text-muted"><?php echo get_string('stat_total', 'local_pdfquizgen'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="mb-0 text-warning" data-stat="pending"><?php echo $stats['pending']; ?></h3>
                        <small class="text-muted"><?php echo get_string('stat_pending', 'local_pdfquizgen'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="mb-0 text-info" data-stat="processing"><?php echo $stats['processing']; ?></h3>
                        <small class="text-muted"><?php echo get_string('stat_processing', 'local_pdfquizgen'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="mb-0 text-success" data-stat="completed"><?php echo $stats['completed']; ?></h3>
                        <small class="text-muted"><?php echo get_string('stat_completed', 'local_pdfquizgen'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="mb-0 text-danger" data-stat="failed"><?php echo $stats['failed']; ?></h3>
                        <small class="text-muted"><?php echo get_string('stat_failed', 'local_pdfquizgen'); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Create New Quiz Section -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo get_string('create_new_quiz', 'local_pdfquizgen'); ?></h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pdffiles)): ?>
                        <div class="alert alert-info">
                            <?php echo get_string('no_pdf_files', 'local_pdfquizgen'); ?>
                        </div>
                    <?php else: ?>
                        <form method="post" action="<?php echo $PAGE->url; ?>" id="pdfquizgen-form">
                            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                            <input type="hidden" name="action" value="create">

                            <div class="form-group">
                                <label><?php echo get_string('select_pdf_files', 'local_pdfquizgen'); ?></label>
                                <div class="pdf-file-selection border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                    <div class="mb-2">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="select-all-files">
                                            <label class="custom-control-label font-weight-bold" for="select-all-files">
                                                <?php echo get_string('select_all', 'local_pdfquizgen'); ?>
                                            </label>
                                        </div>
                                    </div>
                                    <hr class="my-2">
                                    <?php foreach ($pdffiles as $file): ?>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox"
                                                   class="custom-control-input pdf-file-checkbox"
                                                   name="fileids[]"
                                                   value="<?php echo $file->id; ?>"
                                                   id="file-<?php echo $file->id; ?>">
                                            <label class="custom-control-label" for="file-<?php echo $file->id; ?>">
                                                <?php echo s($file->filename); ?>
                                                <?php if ($file->resource_name): ?>
                                                    <span class="text-muted">(<?php echo s($file->resource_name); ?>)</span>
                                                <?php endif; ?>
                                                <small class="text-muted">- <?php echo display_size($file->filesize); ?></small>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <small class="form-text text-muted"><?php echo get_string('select_pdf_help', 'local_pdfquizgen'); ?></small>
                            </div>

                            <div class="form-group">
                                <label for="questioncount"><?php echo get_string('question_count', 'local_pdfquizgen'); ?></label>
                                <input type="number"
                                       name="questioncount"
                                       id="questioncount"
                                       class="form-control"
                                       value="10"
                                       min="1"
                                       max="100"
                                       step="1"
                                       required
                                       pattern="[0-9]*"
                                       inputmode="numeric"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                       title="<?php echo get_string('question_count_help', 'local_pdfquizgen'); ?>">
                                <small class="form-text text-muted"><?php echo get_string('question_count_range', 'local_pdfquizgen'); ?></small>
                            </div>

                            <div class="form-group">
                                <label for="questiontype"><?php echo get_string('question_type', 'local_pdfquizgen'); ?></label>
                                <select name="questiontype" id="questiontype" class="form-control">
                                    <option value="multichoice"><?php echo get_string('multichoice', 'local_pdfquizgen'); ?></option>
                                    <option value="truefalse"><?php echo get_string('truefalse', 'local_pdfquizgen'); ?></option>
                                    <option value="shortanswer"><?php echo get_string('shortanswer', 'local_pdfquizgen'); ?></option>
                                    <option value="mixed"><?php echo get_string('mixed', 'local_pdfquizgen'); ?></option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary" id="pdfquizgen-submit" disabled>
                                <i class="fa fa-magic"></i> <?php echo get_string('generate_quiz', 'local_pdfquizgen'); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Jobs Section -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo get_string('recent_jobs', 'local_pdfquizgen'); ?></h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($jobs)): ?>
                        <div class="p-3 text-muted">
                            <?php echo get_string('no_jobs_yet', 'local_pdfquizgen'); ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?php echo get_string('file', 'local_pdfquizgen'); ?></th>
                                        <th><?php echo get_string('status', 'local_pdfquizgen'); ?></th>
                                        <th><?php echo get_string('created', 'local_pdfquizgen'); ?></th>
                                        <th><?php echo get_string('actions', 'local_pdfquizgen'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jobs as $job): ?>
                                        <tr <?php if ($job->status === 'pending'): ?>data-pending-job="<?php echo $job->id; ?>"<?php endif; ?>>
                                            <td>
                                                <small><?php echo s($job->filename); ?></small><br>
                                                <small class="text-muted">
                                                    <?php echo $job->questioncount; ?> <?php echo get_string('questions', 'local_pdfquizgen'); ?>
                                                    (<?php echo get_string($job->questiontype, 'local_pdfquizgen'); ?>)
                                                </small>
                                            </td>
                                            <td>
                                                <span class="<?php echo local_pdfquizgen_get_status_class($job->status); ?> job-status-badge">
                                                    <?php echo local_pdfquizgen_get_status_text($job->status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo userdate($job->timecreated, '%d/%m/%Y %H:%M'); ?></small>
                                            </td>
                                            <td class="job-actions">
                                                <?php if ($job->status === 'pending'): ?>
                                                    <span class="spinner-border spinner-border-sm text-warning" role="status" title="<?php echo get_string('status_pending', 'local_pdfquizgen'); ?>"></span>
                                                    <small class="text-muted ml-1"><?php echo get_string('status_pending', 'local_pdfquizgen'); ?></small>
                                                <?php elseif ($job->status === 'completed' && $job->quizid): ?>
                                                    <?php
                                                    $cm = get_coursemodule_from_instance('quiz', $job->quizid);
                                                    if ($cm):
                                                    ?>
                                                        <a href="<?php echo new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]); ?>"
                                                           class="btn btn-sm btn-success" title="<?php echo get_string('view_quiz', 'local_pdfquizgen'); ?>">
                                                            <i class="fa fa-eye"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <?php if ($job->status === 'failed'): ?>
                                                    <a href="<?php echo new moodle_url($PAGE->url, ['action' => 'retry', 'jobid' => $job->id, 'sesskey' => sesskey()]); ?>"
                                                       class="btn btn-sm btn-warning" title="<?php echo get_string('retry', 'local_pdfquizgen'); ?>">
                                                        <i class="fa fa-refresh"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <a href="<?php echo new moodle_url($PAGE->url, ['action' => 'delete', 'jobid' => $job->id, 'sesskey' => sesskey()]); ?>"
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('<?php echo get_string('confirm_delete', 'local_pdfquizgen'); ?>')"
                                                   title="<?php echo get_string('delete', 'local_pdfquizgen'); ?>">
                                                    <i class="fa fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Section -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><?php echo get_string('how_it_works', 'local_pdfquizgen'); ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 text-center">
                    <div class="step-icon mb-2">
                        <i class="fa fa-file-pdf-o fa-3x text-danger"></i>
                    </div>
                    <h6>1. <?php echo get_string('step_select_pdf', 'local_pdfquizgen'); ?></h6>
                    <p class="small text-muted"><?php echo get_string('step_select_pdf_desc', 'local_pdfquizgen'); ?></p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="step-icon mb-2">
                        <i class="fa fa-cogs fa-3x text-primary"></i>
                    </div>
                    <h6>2. <?php echo get_string('step_configure', 'local_pdfquizgen'); ?></h6>
                    <p class="small text-muted"><?php echo get_string('step_configure_desc', 'local_pdfquizgen'); ?></p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="step-icon mb-2">
                        <i class="fa fa-magic fa-3x text-info"></i>
                    </div>
                    <h6>3. <?php echo get_string('step_generate', 'local_pdfquizgen'); ?></h6>
                    <p class="small text-muted"><?php echo get_string('step_generate_desc', 'local_pdfquizgen'); ?></p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="step-icon mb-2">
                        <i class="fa fa-check-circle fa-3x text-success"></i>
                    </div>
                    <h6>4. <?php echo get_string('step_review', 'local_pdfquizgen'); ?></h6>
                    <p class="small text-muted"><?php echo get_string('step_review_desc', 'local_pdfquizgen'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.pdfquizgen-dashboard .stats-cards .card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.pdfquizgen-dashboard .step-icon {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 50%;
    display: inline-block;
}
.pdf-file-selection .custom-control {
    padding-top: 0.25rem;
    padding-bottom: 0.25rem;
}
.pdf-file-selection .custom-control-label {
    cursor: pointer;
}
.pdf-file-checkbox:checked + .custom-control-label {
    font-weight: 500;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select All functionality
    var selectAllCheckbox = document.getElementById('select-all-files');
    var fileCheckboxes = document.querySelectorAll('.pdf-file-checkbox');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            fileCheckboxes.forEach(function(checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateSubmitButton();
        });

        // Update "Select All" state when individual checkboxes change
        fileCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                var allChecked = Array.from(fileCheckboxes).every(function(cb) {
                    return cb.checked;
                });
                var someChecked = Array.from(fileCheckboxes).some(function(cb) {
                    return cb.checked;
                });
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = someChecked && !allChecked;
                updateSubmitButton();
            });
        });
    }

    // Form validation - require at least one file selected
    var form = document.getElementById('pdfquizgen-form');
    var submitBtn = document.getElementById('pdfquizgen-submit');

    function updateSubmitButton() {
        if (submitBtn) {
            var anyChecked = Array.from(fileCheckboxes).some(function(cb) {
                return cb.checked;
            });
            submitBtn.disabled = !anyChecked;
        }
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            var anyChecked = Array.from(fileCheckboxes).some(function(cb) {
                return cb.checked;
            });
            if (!anyChecked) {
                e.preventDefault();
                alert('<?php echo addslashes(get_string('select_at_least_one', 'local_pdfquizgen')); ?>');
                return false;
            }
        });
    }

    // Initial state
    updateSubmitButton();
});
</script>

<?php
// Initialize JavaScript for AJAX processing of pending jobs.
$PAGE->requires->js_call_amd('local_pdfquizgen/job_processor', 'init', [$courseid]);

echo $OUTPUT->footer();
