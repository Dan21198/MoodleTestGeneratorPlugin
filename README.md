# MoodleTestGeneratorPlugin for Moodle

A Moodle local plugin that automatically generates quizzes from PDF and Word documents using AI-powered question generation via configurable LLM providers (default: OpenRouter API).

## Features

- **Multi-File Support**: Select and process multiple PDF/Word documents at once
- **PDF Text Extraction**: Multiple extraction methods (pdftotext, Smalot Parser, stream extraction)
- **Word Document Support**: Full support for DOCX and basic DOC file formats
- **AI-Powered Question Generation**: Uses configurable LLM providers with multiple AI model options
- **Multiple Question Types**: Supports Multiple Choice, True/False, Short Answer, and Mixed types
- **Smart Question Distribution**: Questions are distributed across files based on content length
- **Language Detection**: Questions are generated in the same language as the source document
- **Asynchronous Processing**: Real-time progress tracking with AJAX-based job processing
- **Direct Quiz Creation**: Creates Moodle quizzes directly in your course with generated questions
- **Job Management**: Track and manage quiz generation jobs with detailed status
- **Comprehensive Logging**: Full activity logging for auditing and debugging

## Requirements

- Moodle 4.1 or higher
- PHP 8.0 or higher
- OpenRouter API key (get one at https://openrouter.ai/)
- For PDF extraction (one of):
  - `pdftotext` command-line tool (recommended)
  - Smalot PDF Parser library (optional, via Composer)
  - Built-in stream/regex extraction (basic support)

## Installation

### Method 1: Manual Installation

1. Download the plugin files
2. Extract to `/local/quizgen/` directory in your Moodle installation
3. Run the upgrade process:
   ```bash
   php admin/cli/upgrade.php
   ```
   Or navigate to **Site Administration > Notifications** in the web interface

### Method 2: Git Installation

```bash
cd /path/to/moodle/local
git clone <repository-url> quizgen
php admin/cli/upgrade.php
```

### Optional: Install Smalot PDF Parser (Better PDF Support)

```bash
cd /local/quizgen
composer require smalot/pdfparser
```

## Configuration

### 1. Get Your LLM Provider API Key

1. Choose your provider (default: OpenRouter at https://openrouter.ai/)
2. Create an account with that provider
3. Generate an API key
4. Add credits to your account if required by the provider

### 2. Configure Plugin Settings

Navigate to **Site Administration > Plugins > Local Plugins > MoodleTestGeneratorPlugin**

#### LLM Provider Settings
The plugin uses a flexible LLM client architecture that supports multiple AI providers:

| Setting | Default | Description |
|---------|---------|-------------|
| LLM Provider | OpenRouter | Which LLM service to use (OpenRouter is currently active) |
| API Key | - | Your API key for the selected LLM provider |
| AI Model | GPT-4o Mini | AI model to use for generation |
| Custom Model | - | Custom model ID (when "Other" is selected) |
| Timeout | 60s | API request timeout in seconds |
| Max Tokens | 2000 | Maximum tokens for AI response |

**Note:** The plugin was recently refactored to support multiple LLM providers. See [LLM_ARCHITECTURE.md](LLM_ARCHITECTURE.md) for details on the new architecture and how to add additional providers (OpenAI, Anthropic, etc.).

#### Quiz Defaults
| Setting | Default | Description |
|---------|---------|-------------|
| Default Question Count | 10 | Default number of questions |
| Default Question Type | Multiple Choice | Default question type |

#### Processing Settings
| Setting | Default | Description |
|---------|---------|-------------|
| Max PDF Size | 50 MB | Maximum PDF file size |
| Max Text Length | 15000 | Maximum characters to process |
| Enable Logging | Yes | Enable activity logging |
| Max Retries | 3 | Retry attempts on failure |

### 3. Set Permissions

By default, users with `editingteacher` or `manager` roles can use the plugin.

Capability: `local/quizgen:use`

## Usage

### Creating a Quiz

1. Navigate to your course
2. Go to **Course Administration > MoodleTestGeneratorPlugin**
3. **Select files**: Click on PDF/Word documents to select them (multiple selection supported)
4. **Configure options**:
   - Number of questions (1-100)
   - Question type (Multiple Choice, True/False, Short Answer, Mixed)
5. Click **Generate Quiz**
6. Wait for processing (progress is shown in real-time)
7. Access your new quiz when complete!

### Multi-File Selection

- Click files to select/deselect them
- Selected files are highlighted
- Questions are distributed proportionally based on content length
- Minimum 1 question per file with sufficient content (>500 characters)

### Managing Jobs

The main page displays:

**Statistics Panel**:
- **Total**: All jobs created
- **Processing**: Currently being generated
- **Completed**: Successfully created quizzes
- **Failed**: Jobs that encountered errors

**Job Actions**:
- **View Quiz**: Opens the created quiz
- **Retry**: Re-run failed jobs
- **Delete**: Remove job and associated quiz

## Supported File Formats

| Format | Extension | Support Level |
|--------|-----------|---------------|
| PDF | .pdf | Full (with text layer) |
| Word (Modern) | .docx | Full |
| Word (Legacy) | .doc | Basic |

### PDF Requirements
- Must contain selectable text (not scanned images)
- For scanned documents, OCR must be applied first

## AI Models

The plugin currently uses OpenRouter as the default provider, but the LLM architecture is provider-agnostic and ready for other services:

| Model | Best For | Speed | Quality |
|-------|----------|-------|---------|
| `openai/gpt-4o-mini` | Testing, quick generation | Fast | Good |
| `openai/gpt-4o` | Production, best results | Medium | Excellent |
| `anthropic/claude-3.5-sonnet` | Complex content | Medium | Excellent |
| `anthropic/claude-3-haiku` | Speed priority | Fast | Good |
| `google/gemini-2.5-pro` | Long documents | Medium | Very Good |
| `google/gemini-2.5-flash` | Quick generation | Fast | Good |
| `meta-llama/llama-3.1-70b-instruct` | Cost-effective | Medium | Good |
| `mistralai/mistral-large-2512` | European alternative | Medium | Very Good |

Select "Other" to use a custom model ID supported by the selected provider.

## Architecture

### LLM Client Layer Architecture

The plugin uses a provider-agnostic LLM layer. `job_manager.php` does not talk to OpenRouter directly anymore; it asks the factory for a client based on configuration.

```
User / UI
   │
   ▼
job_manager.php
   │
   ▼
llm_factory::create()
   │
   ├─ llm_client_interface.php
   ├─ llm_client_base.php
   └─ provider implementation in `classes/llm/`
      ├─ openrouter_client.php (active)
      ├─ openai_client.php.example (example)
      └─ future providers
   │
   ▼
OpenRouter / OpenAI / Anthropic / other LLM API
```

### File Structure
```
local/quizgen/
├── amd/
│   ├── src/
│   │   └── job_processor.js       # Async job processing
│   └── build/
│       └── job_processor.min.js
├── classes/
│   ├── external/                   # Moodle External API
│   │   ├── process_job.php
│   │   └── get_job_status.php
│   ├── question/                   # Question type handlers
│   │   ├── question_type_factory.php
│   │   ├── question_type_base.php
│   │   ├── multichoice_question.php
│   │   ├── truefalse_question.php
│   │   ├── shortanswer_question.php
│   │   └── question_helper.php
│   ├── task/
│   │   └── cleanup_old_data.php
│   ├── llm/                        # NEW: LLM provider layer
│   │   ├── llm_client_interface.php
│   │   ├── llm_client_base.php
│   │   ├── llm_factory.php
│   │   ├── openrouter_client.php
│   │   ├── openai_client.php.example
│   │   └── README.md
│   ├── file_extractor.php
│   ├── pdf_extractor.php
│   ├── word_extractor.php
│   ├── openrouter_client.php       # DEPRECATED (compatibility only)
│   ├── quiz_generator.php
│   └── job_manager.php
├── db/
│   ├── access.php
│   ├── install.xml
│   ├── services.php
│   └── upgrade.php
├── lang/en/
│   └── local_quizgen.php
├── cli/
│   ├── test_api.php
│   └── test_llm_factory.php
├── index.php
├── logs.php
├── lib.php
├── settings.php
├── version.php
├── LLM_ARCHITECTURE.md
├── MIGRATION_GUIDE.md
├── REFACTORING_QUICK_START.md
└── README.md
```


**Benefits:**
- ✅ Easy to add new providers (OpenAI, Anthropic, local LLMs)
- ✅ Shared code reduces duplication
- ✅ Flexible configuration
- ✅ 100% backward compatible

### Database Schema

#### local_quizgen_jobs
| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| courseid | BIGINT | Course ID |
| userid | BIGINT | User ID |
| fileids | TEXT | JSON array of file IDs |
| filename | VARCHAR | Display filename |
| status | VARCHAR | processing/completed/failed |
| quizid | BIGINT | Created quiz ID |
| questioncount | INT | Requested question count |
| questiontype | VARCHAR | Question type |
| extracted_text | LONGTEXT | Extracted content |
| api_response | LONGTEXT | AI response |
| error_message | TEXT | Error details |
| timecreated | BIGINT | Creation timestamp |
| timemodified | BIGINT | Last modified |
| timecompleted | BIGINT | Completion timestamp |

#### local_quizgen_questions
| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| jobid | BIGINT | Parent job ID |
| questiontext | TEXT | Question content |
| questiontype | VARCHAR | Type of question |
| options | TEXT | JSON options (MCQ) |
| correctanswer | TEXT | Correct answer |
| explanation | TEXT | Answer explanation |
| moodle_questionid | BIGINT | Moodle question ID |
| timecreated | BIGINT | Creation timestamp |

#### local_quizgen_logs
Activity logs for auditing and debugging.

## Troubleshooting

### PDF Extraction Failed
**Solutions**:
1. Ensure PDF contains selectable text (not scanned images)
2. Install pdftotext:
   ```bash
   # Ubuntu/Debian
   sudo apt-get install poppler-utils
   
   # CentOS/RHEL
   sudo yum install poppler-utils
   
   # macOS
   brew install poppler
   ```
3. Install Smalot PDF Parser: `composer require smalot/pdfparser`

### API Errors
| Error | Solution |
|-------|----------|
| 401 Unauthorized | Verify API key |
| 402 Payment Required | Add credits to OpenRouter account |
| 429 Rate Limited | Wait and retry, or use different model |
| Timeout | Increase timeout in settings |

### Questions Not Generated
- Ensure document has sufficient educational content
- Check extracted text quality in job details
- Try a different AI model
- Increase question count request

### Plugin Not Visible
1. Verify installation: **Site Administration > Plugins > Local Plugins**
2. Check capability: `local/quizgen:use`
3. Purge caches: **Site Administration > Development > Purge caches**

## Scheduled Tasks

The plugin includes a cleanup task that removes old data:
- Runs daily via Moodle cron
- Removes completed jobs older than 30 days
- Cleans up orphaned log entries

## Security

- **API Key**: Stored encrypted in Moodle configuration
- **File Access**: Restricted to course context
- **Permissions**: Enforces Moodle capability system
- **HTTPS**: All external API calls use HTTPS
- **Session Validation**: All actions validate sesskey

## Performance Notes

- Text extraction: Synchronous (1-10 seconds per file)
- API calls: Configurable timeout (default 60s)
- Large documents: Truncated to max_text_length
- Multi-file: Processed sequentially for reliability

## Version History

### v1.7.0 (Current) - LLM Client Refactoring
**New Architecture & Multi-Provider Support**
- Complete refactoring of LLM client layer
- Abstracted provider interface for multi-provider support
- New factory pattern for provider instantiation
- Base class with shared functionality (prompt building, JSON parsing)
- Prepared for OpenAI, Anthropic, and other provider integration
- New test script: `cli/test_llm_factory.php`
- 100% backward compatible - automatic configuration migration

### v1.6.0
- Multi-file selection and processing
- Word document support (DOCX/DOC)
- Smart question distribution across files
- Improved job status tracking
- Real-time progress updates

### v1.5.0
- Model selection from popular AI models
- Custom model support
- Improved PDF extraction

### v1.4.0
- Asynchronous job processing
- External API endpoints
- JavaScript-based UI updates

### v1.0.0
- Initial release
- Basic PDF extraction
- Quiz generation

## License

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

## Credits

**Author**: Daniel Horejsi  
**Copyright**: 2025

**Powered by**:
- [OpenRouter](https://openrouter.ai/) - AI model aggregation
- [Moodle](https://moodle.org/) - Learning Platform
- [Smalot PDF Parser](https://github.com/smalot/pdfparser) - PDF parsing (optional)
