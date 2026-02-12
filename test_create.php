<?php
// Test quiz creation end-to-end
// Access: /local/pdfquizgen/test_create.php

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/pdfquizgen/test_create.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Test Quiz Creation');
$PAGE->set_heading('Test Quiz Creation');

echo $OUTPUT->header();

echo '<h2>Test Quiz Creation - Step by Step</h2>';
echo '<pre>';

$courseid = 2;

echo "=== Step 1: Create quiz_generator ===\n";
try {
    $generator = new \local_pdfquizgen\quiz_generator($courseid, $USER->id);
    echo "✓ quiz_generator created\n\n";
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
    die();
}

echo "=== Step 2: Prepare test questions ===\n";
$testquestions = [
    [
        'type' => 'multichoice',
        'question' => 'Test Question 1 - ' . time(),
        'options' => ['Answer A', 'Answer B', 'Answer C', 'Answer D'],
        'correct_answer' => 0,
        'explanation' => 'A is correct'
    ],
    [
        'type' => 'multichoice',
        'question' => 'Test Question 2 - ' . time(),
        'options' => ['Option 1', 'Option 2', 'Option 3', 'Option 4'],
        'correct_answer' => 1,
        'explanation' => 'Option 2 is correct'
    ],
    [
        'type' => 'truefalse',
        'question' => 'Test True/False - ' . time(),
        'correct_answer' => true,
        'explanation' => 'This is true'
    ]
];
echo "✓ Prepared " . count($testquestions) . " test questions\n\n";

echo "=== Step 3: Call create_quiz ===\n";
$quizname = 'Test Quiz - ' . date('Y-m-d H:i:s');
echo "Quiz name: $quizname\n";

$result = $generator->create_quiz($testquestions, $quizname);

echo "\nResult:\n";
echo "  success: " . ($result['success'] ? 'TRUE' : 'FALSE') . "\n";
echo "  quizid: " . ($result['quizid'] ?? 'N/A') . "\n";
echo "  cmid: " . ($result['cmid'] ?? 'N/A') . "\n";
echo "  questioncount: " . ($result['questioncount'] ?? 'N/A') . "\n";
echo "  error: " . ($result['error'] ?? 'none') . "\n";

if ($result['success']) {
    echo "\n=== Step 4: Verify quiz in database ===\n";

    $quiz = $DB->get_record('quiz', ['id' => $result['quizid']]);
    echo "Quiz record: " . ($quiz ? "✓ Found" : "✗ NOT FOUND") . "\n";

    $cm = $DB->get_record('course_modules', ['id' => $result['cmid']]);
    echo "Course module: " . ($cm ? "✓ Found" : "✗ NOT FOUND") . "\n";

    $slots = $DB->get_records('quiz_slots', ['quizid' => $result['quizid']], 'slot ASC');
    echo "Quiz slots: " . count($slots) . "\n";

    foreach ($slots as $slot) {
        $ref = $DB->get_record('question_references', [
            'component' => 'mod_quiz',
            'questionarea' => 'slot',
            'itemid' => $slot->id
        ]);
        echo "  Slot #{$slot->slot}: page={$slot->page}, ref=" . ($ref ? "✓" : "✗") . "\n";
    }

    // Check and delete any existing attempts for this quiz
    $attempts = $DB->get_records('quiz_attempts', ['quiz' => $result['quizid']]);
    if (!empty($attempts)) {
        echo "\n⚠️ Found " . count($attempts) . " existing attempt(s) - deleting...\n";
        $DB->delete_records('quiz_attempts', ['quiz' => $result['quizid']]);
        echo "✓ Deleted old attempts\n";
    }

    echo "\n=== Step 5: Test links ===\n";
    $viewurl = new moodle_url('/mod/quiz/view.php', ['id' => $result['cmid']]);
    echo "View quiz: " . $viewurl->out() . "\n";

    $editurl = new moodle_url('/mod/quiz/edit.php', ['cmid' => $result['cmid']]);
    echo "Edit quiz: " . $editurl->out() . "\n";

} else {
    echo "\n=== QUIZ CREATION FAILED ===\n";
    echo "Error: " . $result['error'] . "\n";
}

echo "\n=== Done ===\n";
echo '</pre>';

if ($result['success']) {
    echo '<p><a href="' . new moodle_url('/mod/quiz/view.php', ['id' => $result['cmid']]) . '" class="btn btn-success">View Created Quiz</a></p>';
    echo '<p><a href="' . new moodle_url('/mod/quiz/edit.php', ['cmid' => $result['cmid']]) . '" class="btn btn-primary">Edit Quiz Questions</a></p>';
}

echo '<p><a href="' . new moodle_url('/local/pdfquizgen/diagnose.php') . '" class="btn btn-warning">Run Diagnostics</a></p>';
echo '<p><a href="' . new moodle_url('/course/view.php', ['id' => $courseid]) . '" class="btn btn-secondary">Return to Course</a></p>';

echo $OUTPUT->footer();

