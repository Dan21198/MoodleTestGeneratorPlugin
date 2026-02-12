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
 * @package    local_pdfquizgen
 * @copyright  2025 Daniel Horejsi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pdfquizgen;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->libdir . '/questionlib.php');

/**
 * Generator for creating Moodle quizzes and questions.
 */
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
    public function __construct($courseid, $userid) {
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
     * @return array Result with 'success', 'quizid', and 'error' keys
     */
    public function create_quiz($questions, $quizname, $quizoptions = []) {
        global $DB;

        try {
            if (empty($questions)) {
                throw new \moodle_exception('error_no_questions_provided', 'local_pdfquizgen', '', null,
                    'No questions were provided to create quiz');
            }

            $categoryid = $this->get_or_create_question_category();
            if (!$categoryid) {
                throw new \moodle_exception('error_category_creation', 'local_pdfquizgen');
            }

            $quiz = $this->create_quiz_activity($quizname, $quizoptions);
            if (!$quiz) {
                throw new \moodle_exception('error_quiz_creation', 'local_pdfquizgen');
            }

            // Create questions and add to quiz
            $questionids = [];
            $errors = [];

            foreach ($questions as $index => $questiondata) {
                try {
                    $questionid = $this->create_question($questiondata, $categoryid);
                    if ($questionid) {
                        $questionids[] = $questionid;
                    } else {
                        $errors[] = "Question $index returned no ID";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Question $index: " . $e->getMessage();
                }
            }

            if (empty($questionids)) {
                $this->delete_quiz($quiz->id);
                $errormsg = 'No questions were created.';
                if (!empty($errors)) {
                    $errormsg .= ' Errors: ' . implode('; ', $errors);
                }
                throw new \moodle_exception('error_no_questions_created', 'local_pdfquizgen', '', null, $errormsg);
            }

            try {
                $this->add_questions_to_quiz($quiz->id, $questionids);
            } catch (\Exception $e) {
                $this->delete_quiz($quiz->id);
                throw new \moodle_exception('error_adding_questions', 'local_pdfquizgen', '', null,
                    'Failed to add questions to quiz: ' . $e->getMessage());
            }

            global $DB;
            $slotcount = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
            if ($slotcount == 0) {
                $this->delete_quiz($quiz->id);
                throw new \moodle_exception('error_no_slots', 'local_pdfquizgen', '', null,
                    'Quiz created but no question slots were added. Questions may not have valid version records.');
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
                'error' => $e->getMessage() . ($e->debuginfo ?? '')
            ];
        }
    }

    /**
     * Get or create a question category for this course.
     *
     * @return int Category ID
     */
    private function get_or_create_question_category() {
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
     * @return object|false Quiz object with cmid or false
     */
    private function create_quiz_activity($name, $options = []) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $section = $DB->get_record('course_sections', [
            'course' => $this->courseid,
            'section' => 0 // Default to General section
        ]);

        if (!$section) {
            $section = $DB->get_record('course_sections', [
                'course' => $this->courseid
            ], '*', IGNORE_MULTIPLE);
        }

        if (!$section) {
            return false;
        }

        $module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);

        $safename = $name ?? '';
        $quizintro = $options['intro'] ?? null;
        if ($quizintro === null) {
            $quizintro = get_string('default_quiz_intro', 'local_pdfquizgen');
        }

        // Step 1: Create the quiz record
        $quiz = new \stdClass();
        $quiz->course = $this->courseid;
        $quiz->name = trim((string)$safename);
        $quiz->intro = trim((string)($quizintro ?? 'Auto-generated quiz'));
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

        $quiz->id = $DB->insert_record('quiz', $quiz);

        // Step 1.5: Create quiz_sections record
        $quizsection = new \stdClass();
        $quizsection->quizid = $quiz->id;
        $quizsection->firstslot = 1;
        $quizsection->heading = '';
        $quizsection->shufflequestions = 0;
        $DB->insert_record('quiz_sections', $quizsection);

        // Step 2: Create course module record
        $newcm = new \stdClass();
        $newcm->course = $this->courseid;
        $newcm->module = $module->id;
        $newcm->instance = $quiz->id;
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

        // Step 3: Add to course section sequence
        $sectionsequence = $section->sequence ? explode(',', $section->sequence) : [];
        $sectionsequence[] = $newcm->id;
        $DB->set_field('course_sections', 'sequence', implode(',', $sectionsequence), ['id' => $section->id]);

        // Step 4: Create context for the module
        \context_module::instance($newcm->id);

        // Step 5: Rebuild course cache
        rebuild_course_cache($this->courseid, true);

        $quiz->cmid = $newcm->id;

        return $quiz;
    }

    /**
     * Check if we're running on Moodle 4.0+.
     *
     * @return bool True if Moodle 4.0+
     */
    private function is_moodle4() {
        global $DB;

        static $is_moodle4 = null;

        if ($is_moodle4 === null) {
            try {
                $DB->get_record_sql("SELECT id FROM {question_bank_entries} LIMIT 1", [], IGNORE_MISSING);
                $is_moodle4 = true;
            } catch (\Exception $e) {
                try {
                    $dbman = $DB->get_manager();
                    $is_moodle4 = $dbman->table_exists('question_bank_entries');
                } catch (\Exception $e2) {
                    $is_moodle4 = false;
                }
            }
        }

        return $is_moodle4;
    }

    /**
     * Create a question.
     */
    private function create_question($questiondata, $categoryid) {
        // FIX: Safely get type with default
        $type = $questiondata['type'] ?? 'multichoice';
        $type = trim((string)$type);

        switch ($type) {
            case 'multichoice':
                return $this->create_multichoice_question($questiondata, $categoryid);
            case 'truefalse':
                return $this->create_truefalse_question($questiondata, $categoryid);
            case 'shortanswer':
                return $this->create_shortanswer_question($questiondata, $categoryid);
            default:
                return $this->create_multichoice_question($questiondata, $categoryid);
        }
    }

    /**
     * Create a multiple choice question.
     */
    private function create_multichoice_question($data, $categoryid) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/question/engine/bank.php');

        $qtext = $data['question'] ?? '';
        $qtext = trim((string)$qtext);
        $explanation = $data['explanation'] ?? '';
        $explanation = trim((string)$explanation);

        $is_moodle4 = $this->is_moodle4();

        $qbe = null;
        if ($is_moodle4) {
            $qbe = new \stdClass();
            $qbe->questioncategoryid = $categoryid;
            $qbe->idnumber = null;
            $qbe->ownerid = $this->user->id;
            $qbe->id = $DB->insert_record('question_bank_entries', $qbe);
        }

        $question = new \stdClass();
        $question->qtype = 'multichoice';
        $question->name = $this->generate_question_name($qtext);
        $question->questiontext = $qtext;
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = $explanation;
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $this->user->id;
        $question->modifiedby = $this->user->id;

        if (!$is_moodle4) {
            $question->category = $categoryid;
            $question->version = make_unique_id_code();
            $question->hidden = 0;
        }

        $question->id = $DB->insert_record('question', $question);

        if ($is_moodle4 && $qbe) {
            $qv = new \stdClass();
            $qv->questionbankentryid = $qbe->id;
            $qv->questionid = $question->id;
            $qv->version = 1;
            $qv->status = 'ready';
            $qv->id = $DB->insert_record('question_versions', $qv);
        }

        $multichoice = new \stdClass();
        $columns = $DB->get_columns('qtype_multichoice_options');
        if (isset($columns['questionid'])) {
            $multichoice->questionid = $question->id;
        } else {
            $multichoice->question = $question->id;
        }
        $multichoice->single = 1;
        $multichoice->shuffleanswers = 1;
        $multichoice->answernumbering = 'abc';
        $multichoice->showstandardinstruction = 0;
        $multichoice->correctfeedback = '';
        $multichoice->correctfeedbackformat = FORMAT_HTML;
        $multichoice->partiallycorrectfeedback = '';
        $multichoice->partiallycorrectfeedbackformat = FORMAT_HTML;
        $multichoice->incorrectfeedback = '';
        $multichoice->incorrectfeedbackformat = FORMAT_HTML;

        $DB->insert_record('qtype_multichoice_options', $multichoice);

        $options = $data['options'] ?? [];
        $correct_val = $data['correct_answer'] ?? '';

        $correct_index = -1;
        if (is_int($correct_val)) {
            $correct_index = $correct_val;
        } else if (is_numeric($correct_val)) {
            // Numeric string like "0", "1", "2"
            $correct_index = (int)$correct_val;
        } else {
            // Text value - will match by comparing text
            $correct_val = trim((string)$correct_val);
        }

        $hasCorrectAnswer = false;
        $answerRecords = [];

        foreach ($options as $index => $option) {
            $opt_text = (string)($option ?? '');
            $opt_text = trim($opt_text);

            $is_correct = false;
            if ($correct_index >= 0 && $index == $correct_index) {
                $is_correct = true;
                $hasCorrectAnswer = true;
            } else if ($correct_index < 0 && $opt_text === $correct_val) {
                $is_correct = true;
                $hasCorrectAnswer = true;
            }

            $answer = new \stdClass();
            $answer->question = $question->id;
            $answer->answer = $opt_text;
            $answer->answerformat = FORMAT_HTML;
            $answer->fraction = $is_correct ? 1.0 : 0.0;
            $answer->feedback = '';
            $answer->feedbackformat = FORMAT_HTML;
            $answerRecords[] = $answer;
        }

        // If no correct answer was found, mark the first one as correct
        if (!$hasCorrectAnswer && !empty($answerRecords)) {
            $answerRecords[0]->fraction = 1.0;
        }

        // Insert all answers
        foreach ($answerRecords as $answer) {
            $DB->insert_record('question_answers', $answer);
        }

        return $question->id;
    }

    /**
     * Create a true/false question.
     */
    private function create_truefalse_question($data, $categoryid) {
        global $DB, $CFG;

        $qtext = $data['question'] ?? '';
        $qtext = trim((string)$qtext);
        $explanation = $data['explanation'] ?? '';
        $explanation = trim((string)$explanation);

        $is_moodle4 = $this->is_moodle4();

        $qbe = null;
        if ($is_moodle4) {
            $qbe = new \stdClass();
            $qbe->questioncategoryid = $categoryid;
            $qbe->idnumber = null;
            $qbe->ownerid = $this->user->id;
            $qbe->id = $DB->insert_record('question_bank_entries', $qbe);
        }

        $question = new \stdClass();
        $question->qtype = 'truefalse';
        $question->name = $this->generate_question_name($qtext);
        $question->questiontext = $qtext;
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = $explanation;
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 1;
        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $this->user->id;
        $question->modifiedby = $this->user->id;

        if (!$is_moodle4) {
            $question->category = $categoryid;
            $question->version = make_unique_id_code();
            $question->hidden = 0;
        }

        $question->id = $DB->insert_record('question', $question);

        if ($is_moodle4 && $qbe) {
            $qv = new \stdClass();
            $qv->questionbankentryid = $qbe->id;
            $qv->questionid = $question->id;
            $qv->version = 1;
            $qv->status = 'ready';
            $qv->id = $DB->insert_record('question_versions', $qv);
        }

        $correct_val = $data['correct_answer'] ?? '';
        if (is_bool($correct_val)) {
            $istrue = $correct_val;
        } else {
            $correct_val = strtolower(trim((string)$correct_val));
            $istrue = ($correct_val === 'true' || $correct_val === 'yes' || $correct_val === '1');
        }

        $ans_true = new \stdClass();
        $ans_true->question = $question->id;
        $ans_true->answer = get_string('true', 'qtype_truefalse');
        $ans_true->answerformat = FORMAT_MOODLE;
        $ans_true->fraction = $istrue ? 1.0 : 0.0;
        $ans_true->feedback = '';
        $ans_true->feedbackformat = FORMAT_HTML;
        $trueid = $DB->insert_record('question_answers', $ans_true);

        $ans_false = new \stdClass();
        $ans_false->question = $question->id;
        $ans_false->answer = get_string('false', 'qtype_truefalse');
        $ans_false->answerformat = FORMAT_MOODLE;
        $ans_false->fraction = $istrue ? 0.0 : 1.0;
        $ans_false->feedback = '';
        $ans_false->feedbackformat = FORMAT_HTML;
        $falseid = $DB->insert_record('question_answers', $ans_false);

        $truefalse = new \stdClass();
        $truefalse->question = $question->id;
        $truefalse->trueanswer = $trueid;
        $truefalse->falseanswer = $falseid;
        $DB->insert_record('question_truefalse', $truefalse);

        return $question->id;
    }

    /**
     * Create a short answer question.
     */
    private function create_shortanswer_question($data, $categoryid) {
        global $DB, $CFG;

        $qtext = $data['question'] ?? '';
        $qtext = trim((string)$qtext);
        $explanation = $data['explanation'] ?? '';
        $explanation = trim((string)$explanation);

        $is_moodle4 = $this->is_moodle4();

        $qbe = null;
        if ($is_moodle4) {
            $qbe = new \stdClass();
            $qbe->questioncategoryid = $categoryid;
            $qbe->idnumber = null;
            $qbe->ownerid = $this->user->id;
            $qbe->id = $DB->insert_record('question_bank_entries', $qbe);
        }

        $question = new \stdClass();
        $question->qtype = 'shortanswer';
        $question->name = $this->generate_question_name($qtext);
        $question->questiontext = $qtext;
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = $explanation;
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $this->user->id;
        $question->modifiedby = $this->user->id;

        if (!$is_moodle4) {
            $question->category = $categoryid;
            $question->version = make_unique_id_code();
            $question->hidden = 0;
        }

        $question->id = $DB->insert_record('question', $question);

        if ($is_moodle4 && $qbe) {
            $qv = new \stdClass();
            $qv->questionbankentryid = $qbe->id;
            $qv->questionid = $question->id;
            $qv->version = 1;
            $qv->status = 'ready';
            $qv->id = $DB->insert_record('question_versions', $qv);
        }

        $options = new \stdClass();
        $columns = $DB->get_columns('qtype_shortanswer_options');
        if (isset($columns['questionid'])) {
            $options->questionid = $question->id;
        } else {
            $options->question = $question->id;
        }
        $options->usecase = 0;
        $DB->insert_record('qtype_shortanswer_options', $options);

        $correct_answer = $data['correct_answer'] ?? '';
        $correct_answer = trim((string)$correct_answer);

        $answer = new \stdClass();
        $answer->question = $question->id;
        $answer->answer = $correct_answer;
        $answer->answerformat = 0;
        $answer->fraction = 1.0;
        $answer->feedback = '';
        $answer->feedbackformat = FORMAT_HTML;
        $DB->insert_record('question_answers', $answer);

        $wildcard = new \stdClass();
        $wildcard->question = $question->id;
        $wildcard->answer = '*';
        $wildcard->answerformat = 0;
        $wildcard->fraction = 0.0;
        $wildcard->feedback = '';
        $wildcard->feedbackformat = FORMAT_HTML;
        $DB->insert_record('question_answers', $wildcard);

        return $question->id;
    }

    /**
     * Add questions to a quiz.
     */
    private function add_questions_to_quiz($quizid, $questionids) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quizid, $quiz->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $use_references = $this->is_moodle4();

        $maxslot = $DB->get_field_sql(
            'SELECT COALESCE(MAX(slot), 0) FROM {quiz_slots} WHERE quizid = ?',
            [$quizid]
        );

        $slot = $maxslot + 1;
        $sumgrades = 0;

        foreach ($questionids as $qid) {
            $question = $DB->get_record('question', ['id' => $qid]);
            if (!$question) {
                debugging("Question ID $qid not found in database", DEBUG_DEVELOPER);
                continue;
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
                $qversion = $DB->get_record('question_versions', ['questionid' => $qid]);
                if ($qversion) {
                    $qref = new \stdClass();
                    $qref->usingcontextid = $context->id;
                    $qref->component = 'mod_quiz';
                    $qref->questionarea = 'slot';
                    $qref->itemid = $slotdata->id;
                    $qref->questionbankentryid = $qversion->questionbankentryid;
                    $qref->version = null; // null = always latest version

                    $DB->insert_record('question_references', $qref);
                } else {
                    debugging("Question version not found for question ID $qid - question may have been created incorrectly", DEBUG_DEVELOPER);

                    $DB->delete_records('quiz_slots', ['id' => $slotdata->id]);
                    continue;
                }
            }

            $sumgrades += $question->defaultmark;
            $slot++;
        }

        $DB->set_field('quiz', 'sumgrades', $sumgrades, ['id' => $quizid]);

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

        rebuild_course_cache($quiz->course, true);
    }

    /**
     * Generate a short name for a question.
     */
    private function generate_question_name($questiontext) {
        $text = (string)($questiontext ?? '');
        $text = trim($text);
        $name = strip_tags($text);
        if (strlen($name) > 50) {
            $name = substr($name, 0, 47) . '...';
        }
        if (empty($name)) {
            $name = 'Question ' . time();
        }
        return $name;
    }

    /**
     * Delete a quiz and all its questions.
     */
    public function delete_quiz($quizid) {
        global $DB;

        $cm = get_coursemodule_from_instance('quiz', $quizid);
        if ($cm) {
            course_delete_module($cm->id);
            return true;
        }
        return false;
    }
}