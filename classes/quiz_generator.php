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
 * Quiz Generator class.
 *
 * This is the main entry point for quiz generation. It orchestrates:
 * - Quiz activity creation
 * - Question creation (delegated to question type handlers)
 * - Adding questions to quizze
 *
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->libdir . '/questionlib.php');

use local_pdfquizgen\question\question_helper;
use local_pdfquizgen\question\question_type_factory;
class quiz_generator {

    /** @var int Course ID */
    private $courseid;

    /** @var object Course object */
    private $course;

    /** @var object User object */
    private $user;

    /**
     * Constructor.
     *
     * @param int $courseid The course ID
     * @param int $userid The user ID creating the quiz
     */
    public function __construct(int $courseid, int $userid) {
        global $DB;

        $this->courseid = $courseid;
        $this->course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $this->user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    }

    /**
     * Create a quiz from generated questions.
     *
     * @param array $questions Array of question data
     * @param string $quizname Name for the quiz
     * @param array $quizoptions Additional quiz options
     * @return array Result with 'success', 'quizid', 'cmid', 'questioncount', and 'error' keys
     */
    public function create_quiz(array $questions, string $quizname, array $quizoptions = []): array {
        global $DB;

        try {
            $this->validate_questions($questions);

            $categoryid = $this->get_or_create_question_category();
            if (!$categoryid) {
                throw new \moodle_exception('error_category_creation', 'local_pdfquizgen');
            }

            $quiz = $this->create_quiz_activity($quizname, $quizoptions);
            if (!$quiz) {
                throw new \moodle_exception('error_quiz_creation', 'local_pdfquizgen');
            }

            $result = $this->create_questions_for_quiz($questions, $categoryid);

            if (empty($result['questionids'])) {
                $this->delete_quiz($quiz->id);
                $errormsg = 'No questions were created.';
                if (!empty($result['errors'])) {
                    $errormsg .= ' Errors: ' . implode('; ', $result['errors']);
                }
                throw new \moodle_exception('error_no_questions_created', 'local_pdfquizgen', '', null, $errormsg);
            }

            $this->add_questions_to_quiz($quiz->id, $result['questionids']);

            $slotcount = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
            if ($slotcount == 0) {
                $this->delete_quiz($quiz->id);
                throw new \moodle_exception('error_no_slots', 'local_pdfquizgen', '', null,
                    'Quiz created but no question slots were added.');
            }

            return [
                'success' => true,
                'quizid' => $quiz->id,
                'cmid' => $quiz->cmid,
                'questioncount' => $slotcount,
                'error' => ''
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'quizid' => 0,
                'cmid' => 0,
                'questioncount' => 0,
                'error' => $e->getMessage() . ($e->debuginfo ?? '')
            ];
        }
    }

    /**
     * Validate questions array.
     *
     * @param array $questions Questions to validate
     * @throws \moodle_exception If validation fails
     */
    private function validate_questions(array $questions): void {
        if (empty($questions)) {
            throw new \moodle_exception('error_no_questions_provided', 'local_pdfquizgen', '', null,
                'No questions were provided to create quiz');
        }
    }

    /**
     * Create questions for a quiz.
     *
     * @param array $questions Question data array
     * @param int $categoryid Question category ID
     * @return array ['questionids' => [], 'errors' => []]
     */
    private function create_questions_for_quiz(array $questions, int $categoryid): array {
        $questionids = [];
        $errors = [];

        foreach ($questions as $index => $questiondata) {
            try {
                $type = $questiondata['type'] ?? 'multichoice';
                $handler = question_type_factory::create($type, $categoryid, $this->user->id);
                $questionid = $handler->create($questiondata);

                if ($questionid) {
                    $questionids[] = $questionid;
                } else {
                    $errors[] = "Question $index returned no ID";
                }
            } catch (\Exception $e) {
                $errors[] = "Question $index: " . $e->getMessage();
            }
        }

        return [
            'questionids' => $questionids,
            'errors' => $errors
        ];
    }

    /**
     * Get or create a question category for this course.
     *
     * @return int Category ID
     */
    private function get_or_create_question_category(): int {
        global $DB;

        $context = \context_course::instance($this->courseid);

        $category = $DB->get_record('question_categories', [
            'contextid' => $context->id,
            'name' => 'PDF Generated Questions'
        ]);

        if ($category) {
            return $category->id;
        }

        $category = new \stdClass();
        $category->name = 'PDF Generated Questions';
        $category->info = 'Questions generated by PDF Quiz Generator';
        $category->infoformat = FORMAT_HTML;
        $category->contextid = $context->id;
        $category->parent = 0;
        $category->sortorder = 999;
        $category->idnumber = null;
        $category->stamp = make_unique_id_code();

        return $DB->insert_record('question_categories', $category);
    }

    /**
     * Create the quiz activity.
     *
     * @param string $name Quiz name
     * @param array $options Quiz options
     * @return object|null Quiz object with cmid or null
     */
    private function create_quiz_activity(string $name, array $options = []): ?object {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $section = $this->get_course_section();
        if (!$section) {
            return null;
        }

        $module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);

        $quiz = $this->build_quiz_record($name, $options);
        $quiz->id = $DB->insert_record('quiz', $quiz);

        // Create quiz section record
        $this->create_quiz_section($quiz->id);

        // Create course module
        $cmid = $this->create_course_module($quiz->id, $module->id, $section);
        $quiz->cmid = $cmid;

        // Rebuild course cache
        rebuild_course_cache($this->courseid, true);

        return $quiz;
    }

    /**
     * Get or create a course section for the quiz.
     *
     * @return object|null Course section or null
     */
    private function get_course_section(): ?object {
        global $DB;

        $section = $DB->get_record('course_sections', [
            'course' => $this->courseid,
            'section' => 0
        ]);

        if (!$section) {
            $section = $DB->get_record('course_sections', [
                'course' => $this->courseid
            ], '*', IGNORE_MULTIPLE);
        }

        return $section ?: null;
    }

    /**
     * Build the quiz database record.
     *
     * @param string $name Quiz name
     * @param array $options Quiz options
     * @return object Quiz record
     */
    private function build_quiz_record(string $name, array $options): object {
        $quizintro = $options['intro'] ?? get_string('default_quiz_intro', 'local_pdfquizgen');

        $quiz = new \stdClass();
        $quiz->course = $this->courseid;
        $quiz->name = trim($name);
        $quiz->intro = trim($quizintro ?? 'Auto-generated quiz');
        $quiz->introformat = FORMAT_HTML;
        $quiz->timeopen = $options['timeopen'] ?? 0;
        $quiz->timeclose = $options['timeclose'] ?? 0;
        $quiz->timelimit = $options['timelimit'] ?? 0;
        $quiz->overduehandling = 'autosubmit';
        $quiz->graceperiod = 0;
        $quiz->preferredbehaviour = 'deferredfeedback';
        $quiz->canredoquestions = 0;
        $quiz->attempts = $options['attempts'] ?? 0;
        $quiz->attemptonlast = 0;
        $quiz->grademethod = 1; // QUIZ_GRADEHIGHEST
        $quiz->decimalpoints = 2;
        $quiz->questiondecimalpoints = -1;
        $quiz->reviewattempt = 69888;
        $quiz->reviewcorrectness = 4352;
        $quiz->reviewmaxmarks = 4352;
        $quiz->reviewmarks = 4352;
        $quiz->reviewspecificfeedback = 4352;
        $quiz->reviewgeneralfeedback = 4352;
        $quiz->reviewrightanswer = 4352;
        $quiz->reviewoverallfeedback = 4352;
        $quiz->questionsperpage = 1;
        $quiz->navmethod = 'free';
        $quiz->shuffleanswers = 1;
        $quiz->sumgrades = 0;
        $quiz->grade = 10;
        $quiz->timecreated = time();
        $quiz->timemodified = time();
        $quiz->quizpassword = '';
        $quiz->subnet = '';
        $quiz->browsersecurity = '-';
        $quiz->delay1 = 0;
        $quiz->delay2 = 0;
        $quiz->showuserpicture = 0;
        $quiz->showblocks = 0;
        $quiz->completionattemptsexhausted = 0;
        $quiz->completionminattempts = 0;

        return $quiz;
    }

    /**
     * Create quiz section record.
     *
     * @param int $quizid Quiz ID
     */
    private function create_quiz_section(int $quizid): void {
        global $DB;

        $quizsection = new \stdClass();
        $quizsection->quizid = $quizid;
        $quizsection->firstslot = 1;
        $quizsection->heading = '';
        $quizsection->shufflequestions = 0;
        $DB->insert_record('quiz_sections', $quizsection);
    }

    /**
     * Create course module for the quiz.
     *
     * @param int $quizid Quiz ID
     * @param int $moduleid Module ID
     * @param object $section Course section
     * @return int Course module ID
     */
    private function create_course_module(int $quizid, int $moduleid, object $section): int {
        global $DB;

        $newcm = new \stdClass();
        $newcm->course = $this->courseid;
        $newcm->module = $moduleid;
        $newcm->instance = $quizid;
        $newcm->section = $section->id;
        $newcm->idnumber = '';
        $newcm->added = time();
        $newcm->visible = 1;
        $newcm->visibleoncoursepage = 1;
        $newcm->visibleold = 1;
        $newcm->groupmode = 0;
        $newcm->groupingid = 0;
        $newcm->completion = 0;
        $newcm->completiongradeitemnumber = null;
        $newcm->completionview = 0;
        $newcm->completionexpected = 0;
        $newcm->showdescription = 0;
        $newcm->availability = null;
        $newcm->deletioninprogress = 0;

        $newcm->id = $DB->insert_record('course_modules', $newcm);

        // Add to course section sequence
        $sectionsequence = $section->sequence ? explode(',', $section->sequence) : [];
        $sectionsequence[] = $newcm->id;
        $DB->set_field('course_sections', 'sequence', implode(',', $sectionsequence), ['id' => $section->id]);

        // Create context for the module
        \context_module::instance($newcm->id);

        return $newcm->id;
    }

    /**
     * Add questions to a quiz.
     *
     * @param int $quizid Quiz ID
     * @param array $questionids Question IDs
     */
    private function add_questions_to_quiz(int $quizid, array $questionids): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quizid, $quiz->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $use_references = question_helper::is_moodle4();

        $maxslot = $DB->get_field_sql(
            'SELECT COALESCE(MAX(slot), 0) FROM {quiz_slots} WHERE quizid = ?',
            [$quizid]
        );

        $slot = $maxslot + 1;
        $sumgrades = 0;

        foreach ($questionids as $qid) {
            $slotAdded = $this->add_question_slot($quizid, $qid, $slot, $context, $use_references);
            if ($slotAdded) {
                $question = $DB->get_record('question', ['id' => $qid]);
                $sumgrades += $question->defaultmark;
                $slot++;
            }
        }

        $DB->set_field('quiz', 'sumgrades', $sumgrades, ['id' => $quizid]);

        $this->update_quiz_grades($quizid);
        rebuild_course_cache($quiz->course, true);
    }

    /**
     * Add a single question slot to the quiz.
     *
     * @param int $quizid Quiz ID
     * @param int $questionid Question ID
     * @param int $slot Slot number
     * @param \context_module $context Module context
     * @param bool $use_references Whether to use Moodle 4 question references
     * @return bool True if slot was added successfully
     */
    private function add_question_slot(int $quizid, int $questionid, int $slot, \context_module $context, bool $use_references): bool {
        global $DB;

        $question = $DB->get_record('question', ['id' => $questionid]);
        if (!$question) {
            return false;
        }

        $page = $slot - 1;

        $slotdata = new \stdClass();
        $slotdata->quizid = $quizid;
        $slotdata->slot = $slot;
        $slotdata->page = $page;
        $slotdata->requireprevious = 0;
        $slotdata->maxmark = $question->defaultmark;
        $slotdata->id = $DB->insert_record('quiz_slots', $slotdata);

        if ($use_references) {
            $qversion = $DB->get_record('question_versions', ['questionid' => $questionid]);
            if (!$qversion) {
                $DB->delete_records('quiz_slots', ['id' => $slotdata->id]);
                return false;
            }

            $qref = new \stdClass();
            $qref->usingcontextid = $context->id;
            $qref->component = 'mod_quiz';
            $qref->questionarea = 'slot';
            $qref->itemid = $slotdata->id;
            $qref->questionbankentryid = $qversion->questionbankentryid;
            $qref->version = null; // null = always latest version

            $DB->insert_record('question_references', $qref);
        }

        return true;
    }

    /**
     * Update quiz grades after adding questions.
     *
     * @param int $quizid Quiz ID
     */
    private function update_quiz_grades(int $quizid): void {
        global $DB;

        $sumgrades = $DB->get_field_sql(
            'SELECT SUM(maxmark) FROM {quiz_slots} WHERE quizid = ?',
            [$quizid]
        );
        $sumgrades = $sumgrades ?: 0;

        $DB->set_field('quiz', 'sumgrades', $sumgrades, ['id' => $quizid]);
        $DB->set_field('quiz', 'grade', $sumgrades, ['id' => $quizid]);

        try {
            if (class_exists('\mod_quiz\grade_calculator')) {
                $quizobj = \mod_quiz\quiz_settings::create($quizid);
                \mod_quiz\grade_calculator::create($quizobj)->recompute_quiz_sumgrades();
            } else if (class_exists('\mod_quiz\quiz_settings')) {
                $quizobj = \mod_quiz\quiz_settings::create($quizid);
                if (method_exists($quizobj, 'get_grade_calculator')) {
                    $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();
                }
            }
        } catch (\Exception $e) {
            // Ignore - sumgrades already set directly
        }

        if (function_exists('quiz_update_grades')) {
            $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
            @quiz_update_grades($quiz);
        }
    }

    /**
     * Delete a quiz and all its data.
     *
     * @param int $quizid Quiz ID
     * @return bool True if deleted
     */
    public function delete_quiz(int $quizid): bool {
        $cm = get_coursemodule_from_instance('quiz', $quizid);
        if ($cm) {
            course_delete_module($cm->id);
            return true;
        }
        return false;
    }
}