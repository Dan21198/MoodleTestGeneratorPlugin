# MoodleTestGeneratorPlugin - Technical Documentation

## Plugin Overview

**Name:** MoodleTestGeneratorPlugin  
**Component:** `local_quizgen`  
**Version:** 1.7.0  
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
│  │  │ llm_factory     │─▶│ LLM layer (`classes/llm/`)          │  ││
│  │  │ (provider select)│  │  ├─ llm_client_interface.php       │  ││
│  │  └─────────────────┘  │  ├─ llm_client_base.php            │  ││
│  │                       │  ├─ openrouter_client.php          │  ││
│  │                       │  ├─ openai_client.php.example      │  ││
│  │                       │  └─ future providers               │  ││
│  │                       └─────────────────────────────────────┘  ││
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
│  │  └─ local_quizgen_jobs, _questions, _logs                    ││
│  └─────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────┘
```

---

## File Structure

```
local/quizgen/
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
│   ├── llm/                       # LLM client layer (NEW)
│   │   ├── llm_client_interface.php    # Provider contract
│   │   ├── llm_client_base.php         # Shared prompt/parsing logic
│   │   ├── llm_factory.php             # Provider factory
│   │   ├── openrouter_client.php       # OpenRouter implementation
│   │   ├── openai_client.php.example    # Example provider implementation
│   │   └── README.md                   # LLM directory docs
│   │
│   ├── file_extractor.php         # File extraction coordinator
│   ├── pdf_extractor.php          # PDF text extraction
│   ├── word_extractor.php         # Word text extraction
│   ├── openrouter_client.php      # DEPRECATED (compatibility only)
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
│       └── local_quizgen.php   # English translations
│
├── cli/                           # CLI scripts
│   ├── test_api.php               # API testing script
│   └── test_llm_factory.php       # LLM factory test script
│
├── index.php                      # Main user interface
├── logs.php                       # Log viewer
├── lib.php                        # Library functions and hooks
├── settings.php                   # Administrator settings
├── version.php                    # Version information
├── LLM_ARCHITECTURE.md            # LLM architecture documentation
├── MIGRATION_GUIDE.md             # LLM migration guide
├── REFACTORING_QUICK_START.md     # Quick start guide
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

### 5. LLM Client Layer (NEW - Refactored Architecture)

**Purpose:** Abstract communication with various LLM providers for question generation.

**Architecture:** The LLM client layer has been completely refactored to support multiple providers:

**Key Components:**
- `llm/llm_client_interface.php` - Contract for all LLM providers
- `llm/llm_client_base.php` - Shared functionality
  - Prompt building (system + user prompts)
  - JSON parsing and validation
  - Question structure validation
  - ~350 lines of reusable code
- `llm/llm_factory.php` - Factory pattern for provider instantiation
- Provider implementations:
  - `llm/openrouter_client.php` - OpenRouter implementation (active)
  - `llm/openai_client.php.example` - Example for adding new providers

**Supported Providers:**
Currently active:
- OpenRouter - Multi-model gateway (100+ models including GPT-4o, Claude, Gemini, etc.)

**Usage (Internal):**
```php
use local_quizgen\llm\llm_factory;

// Factory creates appropriate client based on configuration
$client = llm_factory::create();

// Generate questions
$result = $client->generate_questions($content, $count, $type);

// Test connection
$test = $client->test_connection();
```

**Configuration:**
| Setting | Default | Description |
|---------|---------|-------------|
| `llm_provider` | openrouter | LLM provider to use |
| `llm_api_key` | - | API key for provider |
| `llm_model` | openai/gpt-4o-mini | Model to use |
| `llm_model_custom` | - | Custom model ID |
| `llm_timeout` | 60 | API timeout in seconds |
| `max_tokens` | 2000 | Max response tokens |

**Backward Compatibility:**
Old configuration keys still work as fallback:
- `openrouter_api_key` → `llm_api_key`
- `openrouter_model` → `llm_model`
- `openrouter_model_custom` → `llm_model_custom`
- `openrouter_timeout` → `llm_timeout`

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

### Table: `local_quizgen_jobs`

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

### Table: `local_quizgen_questions`

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

### Table: `local_quizgen_logs`

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
User              index.php       JobManager      FileExtractor     llm_factory       LLM Client     QuizGenerator
    │                 │               │                │                 │                │                  │
    │ 1. Select files │               │                │                 │                │                  │
    │────────────────▶│               │                │                 │                │                  │
    │                 │               │                │                 │                │                  │
    │ 2. Submit form  │               │                │                 │                │                  │
    │────────────────▶│               │                │                 │                │                  │
    │                 │ 3. create_job │                │                 │                │                  │
    │                 │──────────────▶│                │                 │                │                  │
    │                 │               │                │                 │                │                  │
    │                 │ 4. Job ID     │                │                 │                │                  │
    │                 │◀──────────────│                │                 │                │                  │
    │                 │               │                │                 │                │                  │
    │                 │ 5. AJAX:process_job            │                 │                │                  │
    │                 │──────────────▶│                │                 │                │                  │
    │                 │               │                │ 6. extract()    │                │                  │
    │                 │               │───────────────▶│                 │                │                  │
    │                 │               │                │                 │                │                  │
    │                 │               │ 7. Text        │                 │                │                  │
    │                 │               │◀───────────────│                 │                │                  │
    │                 │               │                │ 8. create()    │                │                  │
    │                 │               │────────────────────────────────▶│                │                  │
    │                 │               │                │                 │                │                  │
    │                 │               │                │ 9. generate_questions()         │                  │
    │                 │               │──────────────────────────────────────────────────▶│                  │
    │                 │               │                │                 │                │                  │
    │                 │               │                │ 10. Questions JSON              │                  │
    │                 │               │◀──────────────────────────────────────────────────│                  │
    │                 │               │                │                 │                │                  │
    │                 │               │ 11. create_quiz()                                │                  │
    │                 │               │──────────────────────────────────────────────────▶│                  │
    │                 │               │                │                 │                │                  │
    │                 │               │ 12. Quiz ID, CMID                                │                  │
    │                 │               │◀──────────────────────────────────────────────────│                  │
    │                 │               │                │                 │                │                  │
    │ 13. Result      │               │                │                 │                │                  │
    │◀────────────────│               │                │                 │                │                  │
```

---

## External API

### process_job

**Endpoint:** `local_quizgen_process_job`

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

**Endpoint:** `local_quizgen_get_job_status`

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

#### LLM Provider Settings (NEW - Refactored)
| Setting | Default | Description |
|---------|---------|-------------|
| `llm_provider` | openrouter | LLM provider to use |
| `llm_api_key` | - | API key for selected provider |
| `llm_model` | openai/gpt-4o-mini | Selected AI model |
| `llm_model_custom` | - | Custom model ID (if "Other" selected) |
| `llm_timeout` | 60 | API timeout in seconds |
| `max_tokens` | 2000 | Max tokens for AI response |

**Note:** The plugin was refactored to support multiple LLM providers. Configuration is now provider-agnostic and can be easily extended for OpenAI, Anthropic, or other services. See [LLM_ARCHITECTURE.md](LLM_ARCHITECTURE.md) for details.

**Backward Compatibility:** Old configuration keys (`openrouter_*`) still work as fallback for automatic migration.

#### Quiz Defaults
| Setting | Default | Description |
|---------|---------|-------------|
| `default_question_count` | 10 | Default number of questions |
| `default_question_type` | multichoice | Default question type |

#### Processing Settings
| Setting | Default | Description |
|---------|---------|-------------|
| `max_pdf_size` | 50 | Max PDF size in MB |
| `max_text_length` | 15000 | Max extracted text length |
| `enable_logging` | 1 | Enable activity logging |
| `max_retries` | 3 | Retry count on API error |

### Permissions

**Capability:** `local/quizgen:use`

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
2. **View logs:** `/local/quizgen/logs.php`
3. **Moodle debug:** Set `$CFG->debug = DEBUG_DEVELOPER`
