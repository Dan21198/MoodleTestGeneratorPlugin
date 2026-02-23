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
 * Job processor for PDF Quiz Generator.
 *
 * @module     local_pdfquizgen/job_processor
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    'use strict';

    /**
     * Process a job via AJAX.
     *
     * @param {number} jobId The job ID to process
     * @param {number} courseId The course ID
     * @returns {Promise}
     */
    var processJob = function(jobId, courseId) {
        return Ajax.call([{
            methodname: 'local_pdfquizgen_process_job',
            args: {
                jobid: jobId,
                courseid: courseId
            }
        }])[0];
    };

    /**
     * Get job status via AJAX.
     *
     * @param {number} jobId The job ID
     * @returns {Promise} Promise resolving to the job status
     */
    var getJobStatus = function(jobId) {
        return Ajax.call([{
            methodname: 'local_pdfquizgen_get_job_status',
            args: {
                jobid: jobId
            }
        }])[0];
    };

    /**
     * Update statistics display.
     *
     * @param {string} statName The stat name (pending, completed, failed, etc.)
     * @param {number} delta The change amount
     */
    var updateStats = function(statName, delta) {
        var statElement = document.querySelector('[data-stat="' + statName + '"]');
        if (statElement) {
            var currentValue = parseInt(statElement.textContent, 10) || 0;
            statElement.textContent = Math.max(0, currentValue + delta);
        }
    };

    /**
     * Process a job with UI updates.
     *
     * @param {number} jobId The job ID
     * @param {number} courseId The course ID
     * @param {HTMLElement} element The element to update
     */
    var processJobWithUI = function(jobId, courseId, element) {
        var statusBadge = element.querySelector('.job-status-badge');
        var actionsCell = element.querySelector('.job-actions');

        // Update UI to show processing
        if (statusBadge) {
            statusBadge.className = 'badge badge-info job-status-badge';
            statusBadge.textContent = 'Processing...';
        }

        if (actionsCell) {
            actionsCell.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Processing...';
        }

        // Process the job
        processJob(jobId, courseId)
            .then(function(result) {
                if (result.success) {
                    // Success
                    if (statusBadge) {
                        statusBadge.className = 'badge badge-success job-status-badge';
                        statusBadge.textContent = 'Completed';
                    }

                    if (actionsCell && result.quizurl) {
                        actionsCell.innerHTML = '<a href="' + result.quizurl + '" class="btn btn-sm btn-success">' +
                            '<i class="fa fa-eye"></i> View Quiz</a>';
                    }

                    // Update stats
                    updateStats('processing', -1);
                    updateStats('completed', 1);

                    // Remove processing attribute
                    element.removeAttribute('data-processing-job');
                } else {
                    // Failed
                    if (statusBadge) {
                        statusBadge.className = 'badge badge-danger job-status-badge';
                        statusBadge.textContent = 'Failed';
                    }

                    if (actionsCell) {
                        actionsCell.innerHTML = '<span class="text-danger" title="' +
                            (result.error || 'Unknown error') + '"><i class="fa fa-exclamation-triangle"></i> Error</span>';
                    }

                    // Update stats
                    updateStats('processing', -1);
                    updateStats('failed', 1);
                }
            })
            .catch(function(error) {
                Notification.exception(error);

                if (statusBadge) {
                    statusBadge.className = 'badge badge-danger job-status-badge';
                    statusBadge.textContent = 'Error';
                }

                if (actionsCell) {
                    actionsCell.innerHTML = '<span class="text-danger"><i class="fa fa-exclamation-triangle"></i> Network Error</span>';
                }

                // Update stats
                updateStats('processing', -1);
                updateStats('failed', 1);
            });
    };

    /**
     * Initialize job processor for processing jobs.
     *
     * @param {number} courseId The course ID
     */
    var init = function(courseId) {
        var processingJobs = document.querySelectorAll('[data-processing-job]');

        processingJobs.forEach(function(element) {
            var jobId = parseInt(element.dataset.processingJob, 10);
            if (jobId) {
                processJobWithUI(jobId, courseId, element);
            }
        });
    };

    return {
        init: init,
        processJob: processJob,
        getJobStatus: getJobStatus
    };
});
