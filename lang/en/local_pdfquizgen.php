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
 * Language strings for PDF Quiz Generator plugin.
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'PDF Quiz Generator';
$string['pluginname_desc'] = 'Automatically generate quizzes from PDF course materials using AI';

// Capabilities
$string['pdfquizgen:use'] = 'Use PDF Quiz Generator';
$string['pdfquizgen:manage'] = 'Manage PDF Quiz Generator settings';
$string['pdfquizgen:viewlogs'] = 'View PDF Quiz Generator logs';

// Settings
$string['openrouter_settings'] = 'OpenRouter API Settings';
$string['openrouter_settings_desc'] = 'Configure your OpenRouter API connection for AI-powered question generation.';
$string['openrouter_api_key'] = 'API Key';
$string['openrouter_api_key_desc'] = 'Your OpenRouter API key. Get one at https://openrouter.ai/';
$string['openrouter_model'] = 'AI Model';
$string['openrouter_model_desc'] = 'Select the AI model to use for question generation. Different models have different capabilities, speeds, and costs.';
$string['openrouter_model_other'] = 'Other (specify below)';
$string['openrouter_model_custom'] = 'Custom Model ID';
$string['openrouter_model_custom_desc'] = 'If you selected "Other" above, enter the OpenRouter model ID here (e.g., "mistralai/mixtral-8x7b-instruct"). Find available models at https://openrouter.ai/models';
$string['openrouter_timeout'] = 'API Timeout (seconds)';
$string['openrouter_timeout_desc'] = 'Maximum time to wait for API responses.';
$string['max_tokens'] = 'Max Response Tokens';
$string['max_tokens_desc'] = 'Maximum tokens for AI response. Lower values use fewer credits but may truncate output. Recommended: 1000-2000 for 5-10 questions.';

$string['quiz_defaults'] = 'Default Quiz Settings';
$string['quiz_defaults_desc'] = 'Default settings for generated quizzes.';
$string['default_question_count'] = 'Default Question Count';
$string['default_question_count_desc'] = 'Default number of questions to generate.';
$string['default_question_type'] = 'Default Question Type';
$string['default_question_type_desc'] = 'Default type of questions to generate.';

$string['pdf_settings'] = 'Document Processing Settings';
$string['pdf_settings_desc'] = 'Settings for PDF and Word document text extraction.';
$string['max_pdf_size'] = 'Maximum File Size (MB)';
$string['max_pdf_size_desc'] = 'Maximum file size for document processing.';
$string['max_text_length'] = 'Maximum Text Length';
$string['max_text_length_desc'] = 'Maximum characters to extract from documents for processing.';

$string['advanced_settings'] = 'Advanced Settings';
$string['advanced_settings_desc'] = 'Advanced configuration options.';
$string['enable_logging'] = 'Enable Logging';
$string['enable_logging_desc'] = 'Log all plugin activities for debugging.';
$string['max_retries'] = 'Max API Retries';
$string['max_retries_desc'] = 'Number of retry attempts for failed API calls.';

// Question types
$string['multichoice'] = 'Multiple Choice';
$string['truefalse'] = 'True/False';
$string['shortanswer'] = 'Short Answer';
$string['mixed'] = 'Mixed Types';

// Status
$string['status_processing'] = 'Processing';
$string['status_completed'] = 'Completed';
$string['status_failed'] = 'Failed';

// Main page
$string['create_new_quiz'] = 'Create New Quiz';
$string['select_pdf'] = 'Select Document';
$string['select_pdf_files'] = 'Select Documents';
$string['select_pdf_help'] = 'Select one or more PDF or Word files. All selected files will be combined to create a single quiz.';
$string['select_all'] = 'Select All';
$string['select_at_least_one'] = 'Please select at least one document.';
$string['question_count'] = 'Number of Questions';
$string['question_count_help'] = 'Enter a number between 1 and 100';
$string['question_count_range'] = 'Enter a number between 1 and 100';
$string['question_type'] = 'Question Type';
$string['questions'] = 'questions';
$string['generate_quiz'] = 'Generate Quiz';
$string['recent_jobs'] = 'Recent Jobs';
$string['file'] = 'File';
$string['status'] = 'Status';
$string['created'] = 'Created';
$string['actions'] = 'Actions';
$string['view_quiz'] = 'View Quiz';
$string['retry'] = 'Retry';
$string['delete'] = 'Delete';
$string['confirm_delete'] = 'Are you sure you want to delete this job and its associated quiz?';

// Statistics
$string['stat_total'] = 'Total';
$string['stat_processing'] = 'Processing';
$string['stat_completed'] = 'Completed';
$string['stat_failed'] = 'Failed';

// How it works
$string['how_it_works'] = 'How It Works';
$string['step_select_pdf'] = 'Select Document';
$string['step_select_pdf_desc'] = 'Choose PDF or Word files from your course materials';
$string['step_configure'] = 'Configure';
$string['step_configure_desc'] = 'Set the number and type of questions';
$string['step_generate'] = 'Generate';
$string['step_generate_desc'] = 'AI extracts content and creates questions';
$string['step_review'] = 'Review';
$string['step_review_desc'] = 'Review and use your new quiz';

// Messages
$string['no_pdf_files'] = 'No PDF or Word files found in this course. Please upload PDF or Word documents to your course first.';
$string['no_jobs_yet'] = 'No quiz generation jobs yet. Create your first quiz above!';
$string['job_queued'] = 'Quiz generation job queued. Processing will start automatically...';
$string['job_queued_multi'] = 'Quiz generation job queued with {$a} files. Processing will start automatically...';
$string['job_created_success'] = 'Quiz created successfully! <a href="{$a->cmid}">View Quiz</a> ({$a->questioncount} questions)';
$string['job_created_error'] = 'Failed to create quiz: {$a->error}';
$string['job_deleted_success'] = 'Job deleted successfully.';
$string['job_deleted_error'] = 'Failed to delete job.';
$string['job_retried_success'] = 'Job retried successfully! <a href="{$a->cmid}">View Quiz</a>';
$string['job_retried_error'] = 'Failed to retry job: {$a->error}';
$string['job_not_processing'] = 'Job is not in processing state and cannot be processed.';

// Errors
$string['error_not_configured'] = 'PDF Quiz Generator is not configured. Please contact your administrator.';
$string['error_api_not_configured'] = 'OpenRouter API key is not configured.';
$string['error_file_not_found'] = 'File not found.';
$string['error_file_too_large'] = 'File is too large. Maximum size is {$a} MB.';
$string['error_invalid_mimetype'] = 'Invalid file type. Only PDF and Word documents are supported.';
$string['error_empty_file'] = 'File is empty.';
$string['error_extraction_failed'] = 'Failed to extract text from document. The file may be corrupted.';
$string['error_no_text_extracted'] = 'Could not extract readable text from this document. PDF files may be scanned or image-based, encrypted, or use unsupported encoding. Word files must contain actual text content.';
$string['error_category_creation'] = 'Failed to create question category.';
$string['error_quiz_creation'] = 'Failed to create quiz activity.';
$string['error_no_questions_created'] = 'No questions were created. Please try again.';
$string['error_no_questions_provided'] = 'No questions were provided by the AI. Please try again.';
$string['error_adding_questions'] = 'Failed to add questions to quiz.';
$string['error_no_slots'] = 'Quiz was created but no questions were added to it.';

// Logs
$string['viewlogs'] = 'View Logs';
$string['filter_by_course'] = 'Filter by Course';
$string['all_courses'] = 'All Courses';
$string['filter'] = 'Filter';
$string['time'] = 'Time';
$string['user'] = 'User';
$string['course'] = 'Course';
$string['action'] = 'Action';
$string['details'] = 'Details';
$string['no_logs'] = 'No logs found.';
$string['back_to_settings'] = 'Back to Settings';

// Quiz defaults
$string['generated_quiz_name'] = '{$a->filename} - Quiz ({$a->date})';
$string['generated_questions'] = 'Generated Questions';
$string['generated_questions_info'] = 'Questions automatically generated from PDF materials';
$string['default_quiz_intro'] = '<p>This quiz was automatically generated from course materials.</p>';

// Scheduled tasks
$string['task_process_jobs'] = 'Process PDF Quiz Generator jobs';
$string['task_cleanup_old_data'] = 'Clean up old PDF Quiz Generator data';
