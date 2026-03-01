# MoodleTestGeneratorPlugin for Moodle

A Moodle local plugin that automatically generates quizzes from PDF and Word documents using AI-powered question generation via OpenRouter API.

## Features

- **Multi-File Support**: Select and process multiple PDF/Word documents at once
- **PDF Text Extraction**: Multiple extraction methods (pdftotext, Smalot Parser, stream extraction)
- **Word Document Support**: Full support for DOCX and basic DOC file formats
- **AI-Powered Question Generation**: Uses OpenRouter API with multiple AI model options
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
2. Extract to `/local/pdfquizgen/` directory in your Moodle installation
3. Run the upgrade process:
   ```bash
   php admin/cli/upgrade.php
   ```
   Or navigate to **Site Administration > Notifications** in the web interface

### Method 2: Git Installation

```bash
cd /path/to/moodle/local
git clone <repository-url> pdfquizgen
php admin/cli/upgrade.php
```

### Optional: Install Smalot PDF Parser (Better PDF Support)

```bash
cd /local/pdfquizgen
composer require smalot/pdfparser
```

## Configuration

### 1. Get OpenRouter API Key

1. Visit https://openrouter.ai/
2. Create an account
3. Generate an API key
4. Add credits to your account (required for API usage)

### 2. Configure Plugin Settings

Navigate to **Site Administration > Plugins > Local Plugins > MoodleTestGeneratorPlugin**

#### OpenRouter API Settings
| Setting | Default | Description |
|---------|---------|-------------|
| API Key | - | Your OpenRouter API key |
| Model | GPT-4o Mini | AI model to use for generation |
| Custom Model | - | Custom model ID (when "Other" is selected) |
| Timeout | 60s | API request timeout |
| Max Tokens | 2000 | Maximum tokens for AI response |

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

Capability: `local/pdfquizgen:use`

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

The plugin supports multiple AI models via OpenRouter:

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

Select "Other" to use any model available on OpenRouter by entering the model ID.

## Question Types

### Multiple Choice
- 4 answer options
- Single correct answer
- Explanation for correct answer
- Randomized option order

### True/False
- Boolean statement questions
- Clear correct answer
- Explanation provided

### Short Answer
- Text input response
- Multiple acceptable answers
- Case-insensitive matching

### Mixed
- Combination of all types
- AI selects appropriate type per question

## Architecture

### File Structure
```
local/pdfquizgen/
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
│   ├── file_extractor.php          # File extraction coordinator
│   ├── pdf_extractor.php           # PDF text extraction
│   ├── word_extractor.php          # Word text extraction
│   ├── openrouter_client.php       # OpenRouter API client
│   ├── quiz_generator.php          # Quiz creation orchestrator
│   └── job_manager.php             # Job lifecycle management
├── db/
│   ├── access.php                  # Capabilities
│   ├── install.xml                 # Database schema
│   ├── services.php                # External services
│   └── upgrade.php                 # Database migrations
├── lang/en/
│   └── local_pdfquizgen.php
├── index.php                       # Main interface
├── logs.php                        # Log viewer
├── lib.php                         # Library functions & hooks
├── settings.php                    # Admin settings
└── version.php                     # Plugin version info
```

### Database Schema

#### local_pdfquizgen_jobs
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

#### local_pdfquizgen_questions
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

#### local_pdfquizgen_logs
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
2. Check capability: `local/pdfquizgen:use`
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

### v1.6.0 (Current)
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
