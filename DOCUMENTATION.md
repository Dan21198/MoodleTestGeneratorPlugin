# MoodleTestGeneratorPlugin - Technical Documentation

## Plugin Overview

**Name:** MoodleTestGeneratorPlugin  
**Component:** `local_pdfquizgen`  
**Version:** 1.6.0  
**Author:** Daniel Horejší  
**License:** GNU GPL v3  
**Minimum Moodle Version:** 4.1 (2022112800)

### Purpose

MoodleTestGeneratorPlugin is a local plugin for Moodle that automatically generates quizzes from PDF and Word documents using AI (artificial intelligence) via the OpenRouter API. The plugin enables educators to quickly create test questions from educational materials without manual input.

---

## System Architecture

### High-Level Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                         USER INTERFACE                              │
│                           (index.php)                               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────────────────┐ │
│  │ File        │───▶│ Job Manager │───▶│ External API            │ │
│  │ Selection   │    │             │    │ (process_job.php)       │ │
│  └─────────────┘    └──────┬──────┘    └────────────┬────────────┘ │
│                            │                        │               │
│                            ▼                        ▼               │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │                    FILE PROCESSING                              ││
│  │  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ ││
│  │  │ File Extractor  │─▶│ PDF Extractor   │  │ Word Extractor  │ ││
│  │  │ (coordinator)   │  │ (pdftotext,     │  │ (DOCX/DOC)      │ ││
│  │  └─────────────────┘  │ Smalot, regex)  │  └─────────────────┘ ││
│  │                       └─────────────────┘                       ││
│  └─────────────────────────────────────────────────────────────────┘│
│                            │                                        │
│                            ▼                                        │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │                    QUESTION GENERATION                          ││
│  │  ┌─────────────────┐  ┌─────────────────────────────────────┐  ││
│  │  │ OpenRouter      │─▶│ AI Models (GPT-4o, Claude, Gemini)  │  ││
│  │  │ Client          │  └─────────────────────────────────────┘  ││
│  │  └─────────────────┘                                            ││
│  └─────────────────────────────────────────────────────────────────┘│
│                            │                                        │
│                            ▼                                        │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │                    QUIZ CREATION                                ││
│  │  ┌─────────────────┐  ┌─────────────────────────────────────┐  ││
│  │  │ Quiz Generator  │─▶│ Question Type Factory               │  ││
│  │  │ (orchestration) │  │  ├─ multichoice_question            │  ││
│  │  └─────────────────┘  │  ├─ truefalse_question              │  ││
│  │                       │  └─ shortanswer_question            │  ││
│  │                       └─────────────────────────────────────┘  ││
│  └─────────────────────────────────────────────────────────────────┘│
│                            │                                        │
│                            ▼                                        │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │                    MOODLE DATABASE                              ││
│  │  ├─ quiz, quiz_slots                                            ││
│  │  ├─ question, question_versions, question_bank_entries          ││
│  │  ├─ question_answers, qtype_*_options                           ││
│  │  └─ local_pdfquizgen_jobs, _questions, _logs                    ││
│  └─────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────┘
```

---

## File Structure

```
local/pdfquizgen/
├── amd/                           # AMD JavaScript modules
│   ├── src/
│   │   └── job_processor.js       # Asynchronous job processing
│   └── build/
│       └── job_processor.min.js   # Minified version
│
├── classes/                       # PHP classes (PSR-4 autoloading)
│   ├── external/                  # Moodle External API
│   │   ├── process_job.php        # AJAX endpoint for processing
│   │   └── get_job_status.php     # AJAX endpoint for job status
│   │
│   ├── question/                  # Question type factory
│   │   ├── question_type_factory.php   # Factory pattern
│   │   ├── question_type_base.php      # Abstract base class
│   │   ├── multichoice_question.php    # Multiple choice handler
│   │   ├── truefalse_question.php      # True/false handler
│   │   ├── shortanswer_question.php    # Short answer handler
│   │   └── question_helper.php         # Helper functions
│   │
│   ├── task/                      # Scheduled tasks
│   │   └── cleanup_old_data.php   # Old data cleanup
│   │
│   ├── util/                      # Utility classes
│   │   └── text_helper.php        # Text processing utilities
│   │
│   ├── file_extractor.php         # File extraction coordinator
│   ├── pdf_extractor.php          # PDF text extraction
│   ├── word_extractor.php         # Word text extraction
│   ├── openrouter_client.php      # OpenRouter API client
│   ├── quiz_generator.php         # Quiz generator
│   └── job_manager.php            # Job management
│
├── db/                            # Database definitions
│   ├── access.php                 # Permission definitions
│   ├── install.xml                # Table schema
│   ├── services.php               # External services
│   └── upgrade.php                # Database migrations
│
├── lang/                          # Language files
│   └── en/
│       └── local_pdfquizgen.php   # English translations
│
├── cli/                           # CLI scripts
│   └── test_api.php               # API testing script
│
├── index.php                      # Main user interface
├── logs.php                       # Log viewer
├── lib.php                        # Library functions and hooks
├── settings.php                   # Administrator settings
├── version.php                    # Version information
├── thirdpartylibs.xml             # Third-party library declarations
└── README.md                      # Documentation (English)
```

---

## System Components

### 1. Job Manager (`job_manager.php`)

**Purpose:** Central management of quiz generation job lifecycle.

**Main Methods:**
- `create_job()` - Create a new job
- `process_job()` - Process job (extraction, generation, quiz creation)
- `complete_job()` - Mark job as complete
- `fail_job()` - Mark job as failed
- `delete_job()` - Delete a job
- `get_job()` - Get job details
- `get_jobs_for_course()` - List jobs for a course
- `get_job_statistics()` - Get job statistics

**Job States:**
- `processing` - Job is being processed
- `completed` - Job completed successfully
- `failed` - Job failed

### 2. File Extractor (`file_extractor.php`)

**Purpose:** Coordinator for text extraction from various file types.

**Supported Formats:**
- PDF (`.pdf`)
- Word documents (`.docx`, `.doc`)

**Logic:**
```php
public function extract($fileid) {
    $file = $this->get_stored_file($fileid);
    $mimetype = $file->get_mimetype();
    
    if (strpos($mimetype, 'pdf') !== false) {
        return $this->pdf_extractor->extract_from_storedfile($file);
    } elseif (strpos($mimetype, 'word') !== false) {
        return $this->word_extractor->extract_from_storedfile($file);
    }
    
    throw new \moodle_exception('unsupported_file_type');
}
```

### 3. PDF Extractor (`pdf_extractor.php`)

**Purpose:** Extract text from PDF files using multiple methods.

**Extraction Methods (in priority order):**

1. **pdftotext** (best results)
   - System command from `poppler-utils` package
   - Requires server installation
   
2. **Smalot PDF Parser** (good results)
   - PHP library installed via Composer
   - `composer require smalot/pdfparser`
   
3. **Stream extraction** (basic)
   - FlateDecode/ASCIIHexDecode stream decompression
   - Regex text parsing
   
4. **Basic extraction** (fallback)
   - Simple extraction without decompression

**Text Cleaning:**
- Encoding conversion to UTF-8
- Invalid character removal
- Line normalization
- Whitespace cleanup

### 4. Word Extractor (`word_extractor.php`)

**Purpose:** Extract text from Word documents.

**DOCX Format:**
- Parsing `word/document.xml` from ZIP archive
- Text extraction from `<w:t>` elements

**DOC Format (legacy):**
- Binary parsing
- Regex text extraction

### 5. OpenRouter Client (`openrouter_client.php`)

**Purpose:** Communication with OpenRouter API for question generation.

**Configuration:**
- API key
- Model (GPT-4o, Claude, Gemini, etc.)
- Timeout
- Max tokens

**Supported Models:**
| Model | Description |
|-------|-------------|
| `openai/gpt-4o-mini` | Fast and affordable |
| `openai/gpt-4o` | Highest quality |
| `anthropic/claude-3.5-sonnet` | Excellent quality |
| `anthropic/claude-3-haiku` | Fast Claude |
| `google/gemini-2.5-pro` | For long content |
| `google/gemini-2.5-flash` | Fast Google |
| `meta-llama/llama-3.1-70b-instruct` | Open source |
| `mistralai/mistral-large-2512` | European alternative |

**Prompt Structure:**
```php
$systemPrompt = "You are an expert educational content creator...";
$userPrompt = "Based on the following educational content, generate {$count} 
               {$type} questions in the same language as the content...";
```

**Response Processing:**
- JSON parsing from markdown blocks
- Question structure validation
- API error handling

### 6. Quiz Generator (`quiz_generator.php`)

**Purpose:** Orchestration of quiz creation in Moodle.

**Creation Steps:**
1. Validate input questions
2. Get/create question category
3. Create quiz activity
4. Create course module
5. Create individual questions (delegated to question handlers)
6. Add questions to quiz (quiz_slots)
7. Calculate sumgrades

### 7. Question Type Factory (`question_type_factory.php`)

**Purpose:** Factory pattern for creating appropriate handlers based on question type.

**Supported Types:**
- `multichoice` - Multiple choice question
- `truefalse` - True/False
- `shortanswer` - Short answer
- `mixed` - Combination of types

**Usage Example:**
```php
$handler = question_type_factory::create('multichoice', $categoryid, $userid);
$questionid = $handler->create($questiondata);
```

---

## Database Schema

### Table: `local_pdfquizgen_jobs`

Stores quiz generation job information.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `courseid` | BIGINT | Course ID |
| `userid` | BIGINT | User ID |
| `fileid` | BIGINT | File ID (legacy) |
| `fileids` | TEXT | JSON array of file IDs |
| `filename` | VARCHAR(255) | Filename |
| `status` | VARCHAR(20) | Job status |
| `quizid` | BIGINT | Created quiz ID |
| `questioncount` | INT | Requested question count |
| `questiontype` | VARCHAR(20) | Question type |
| `extracted_text` | LONGTEXT | Extracted text |
| `api_response` | LONGTEXT | API response |
| `error_message` | TEXT | Error message |
| `timecreated` | BIGINT | Creation time |
| `timemodified` | BIGINT | Last modified time |
| `timecompleted` | BIGINT | Completion time |

### Table: `local_pdfquizgen_questions`

Stores generated questions.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `jobid` | BIGINT | Parent job ID |
| `questiontext` | TEXT | Question text |
| `questiontype` | VARCHAR(20) | Question type |
| `options` | TEXT | JSON options (MCQ) |
| `correctanswer` | TEXT | Correct answer |
| `explanation` | TEXT | Answer explanation |
| `moodle_questionid` | BIGINT | Moodle question ID |
| `timecreated` | BIGINT | Creation time |

### Table: `local_pdfquizgen_logs`

Logging table for audit.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `jobid` | BIGINT | Job ID |
| `courseid` | BIGINT | Course ID |
| `userid` | BIGINT | User ID |
| `action` | VARCHAR(50) | Action type |
| `details` | TEXT | Details |
| `timecreated` | BIGINT | Creation time |

---

## Quiz Creation Data Flow

### Sequence Diagram

```
User              index.php       JobManager      FileExtractor     OpenRouterClient    QuizGenerator
    │                 │               │                │                   │                  │
    │ 1. Select files │               │                │                   │                  │
    │────────────────▶│               │                │                   │                  │
    │                 │               │                │                   │                  │
    │ 2. Submit form  │               │                │                   │                  │
    │────────────────▶│               │                │                   │                  │
    │                 │ 3. create_job │                │                   │                  │
    │                 │──────────────▶│                │                   │                  │
    │                 │               │                │                   │                  │
    │                 │ 4. Job ID     │                │                   │                  │
    │                 │◀──────────────│                │                   │                  │
    │                 │               │                │                   │                  │
    │                 │ 5. AJAX:process_job            │                   │                  │
    │                 │──────────────▶│                │                   │                  │
    │                 │               │                │                   │                  │
    │                 │               │ 6. extract()   │                   │                  │
    │                 │               │───────────────▶│                   │                  │
    │                 │               │                │                   │                  │
    │                 │               │ 7. Text        │                   │                  │
    │                 │               │◀───────────────│                   │                  │
    │                 │               │                │                   │                  │
    │                 │               │ 8. generate_questions()            │                  │
    │                 │               │───────────────────────────────────▶│                  │
    │                 │               │                │                   │                  │
    │                 │               │ 9. Questions JSON                  │                  │
    │                 │               │◀───────────────────────────────────│                  │
    │                 │               │                │                   │                  │
    │                 │               │ 10. create_quiz()                                     │
    │                 │               │────────────────────────────────────────────────────── ▶│
    │                 │               │                │                   │                  │
    │                 │               │ 11. Quiz ID, CMID                                     │
    │                 │               │◀──────────────────────────────────────────────────────│
    │                 │               │                │                   │                  │
    │ 12. Result      │               │                │                   │                  │
    │◀────────────────│               │                │                   │                  │
```

---

## External API

### process_job

**Endpoint:** `local_pdfquizgen_process_job`

**Parameters:**
- `jobid` (int) - Job ID to process

**Return Value:**
```json
{
    "success": true,
    "status": "completed",
    "quizid": 42,
    "cmid": 15,
    "questioncount": 5,
    "message": "Quiz successfully created"
}
```

### get_job_status

**Endpoint:** `local_pdfquizgen_get_job_status`

**Parameters:**
- `jobid` (int) - Job ID

**Return Value:**
```json
{
    "status": "completed",
    "quizid": 42,
    "cmid": 15,
    "error": ""
}
```

---

## Configuration

### Administrator Settings

Path: **Site Administration > Plugins > Local Plugins > MoodleTestGeneratorPlugin**

| Setting | Default | Description |
|---------|---------|-------------|
| `openrouter_api_key` | - | OpenRouter API key |
| `openrouter_model` | gpt-4o-mini | Selected AI model |
| `openrouter_model_custom` | - | Custom model (if "other") |
| `openrouter_timeout` | 60 | API timeout in seconds |
| `max_tokens` | 2000 | Max tokens for response |
| `default_question_count` | 10 | Default question count |
| `default_question_type` | multichoice | Default question type |
| `max_pdf_size` | 50 | Max PDF size in MB |
| `max_text_length` | 15000 | Max extracted text length |
| `enable_logging` | 1 | Enable logging |
| `max_retries` | 3 | Retry count on error |

### Permissions

**Capability:** `local/pdfquizgen:use`

**Default Roles:**
- `editingteacher`
- `manager`

---

## Security Aspects

1. **API Key Storage:** Stored in Moodle configuration (encrypted database)
2. **File Access:** Only files within course context
3. **Permissions:** Respects Moodle capability system
4. **Input Validation:** All inputs are validated
5. **HTTPS:** All API calls use HTTPS
6. **Session Control:** sesskey verification on every action

---

## Performance and Limits

- **PDF extraction:** Synchronous processing (a few seconds)
- **API timeout:** Configurable (default 60s)
- **Large PDFs:** Truncated to max_text_length
- **Multi-file:** Question distribution based on text length

### Question Distribution Formula

```
questions_per_file = (file_text_length / total_text_length) * total_questions
```

With a minimum of 1 question for files > 500 characters.

---

## Troubleshooting

### Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| PDF extraction failed | PDF doesn't contain text | Use OCR or different document |
| API Error 402 | Insufficient credits | Top up OpenRouter account |
| API Error 401 | Invalid API key | Verify API key |
| JSON parse error | AI returned invalid format | Try different model |
| Undefined array key -1 | Quiz slots error | Purge cache |

### Debugging

1. **Enable logging** in settings
2. **View logs:** `/local/pdfquizgen/logs.php`
3. **Moodle debug:** Set `$CFG->debug = DEBUG_DEVELOPER`

---

## Conclusion

MoodleTestGeneratorPlugin provides a complete solution for automated quiz generation from educational materials. The plugin leverages modern AI models and is designed with extensibility, security, and performance in mind for the Moodle LMS environment.

