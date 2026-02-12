# PDF Quiz Generator for Moodle

A Moodle local plugin that automatically generates quizzes from PDF course materials using AI-powered question generation via OpenRouter API.

## Features

- **PDF Text Extraction**: Automatically extracts text content from PDF files in your course
- **AI-Powered Question Generation**: Uses OpenRouter API to generate high-quality quiz questions
- **Multiple Question Types**: Supports Multiple Choice, True/False, Short Answer, and Mixed question types
- **Direct Quiz Creation**: Creates Moodle quizzes directly in your course with generated questions
- **Job Management**: Track and manage quiz generation jobs with detailed status
- **Retry Failed Jobs**: Easily retry failed quiz generation attempts
- **Comprehensive Logging**: Full activity logging for auditing and debugging

## Requirements

- Moodle 4.1 or higher
- PHP 7.4 or higher
- OpenRouter API key (get one at https://openrouter.ai/)
- One of the following PDF text extraction methods:
  - `pdftotext` command-line tool (recommended)
  - Smalot PDF Parser library (optional, via Composer)
  - Built-in regex fallback (basic support)

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
4. Add credits to your account (if required)

### 2. Configure Plugin Settings

1. Go to **Site Administration > Plugins > Local Plugins > PDF Quiz Generator**
2. Enter your OpenRouter API Key
3. Select your preferred AI Model (default: GPT-4o Mini)
4. Adjust other settings as needed:
   - Default question count
   - Default question type
   - PDF size limits
   - API timeout

### 3. Set Permissions

By default, users with `editingteacher` or `manager` roles can use the plugin. Adjust permissions at:

**Site Administration > Users > Permissions > Define Roles**

## Usage

### Creating a Quiz from PDF

1. Navigate to your course
2. Go to **Course Administration > PDF Quiz Generator**
   (or find it in the course settings menu)
3. Select a PDF file from your course materials
4. Configure options:
   - Number of questions (5-50)
   - Question type (Multiple Choice, True/False, Short Answer, Mixed)
5. Click **Generate Quiz**
6. Wait for processing (may take 30-60 seconds)
7. View your new quiz when complete!

### Managing Jobs

The main page shows statistics and recent jobs:
- **Total**: All jobs
- **Pending**: Waiting to be processed
- **Processing**: Currently being generated
- **Completed**: Successfully created quizzes
- **Failed**: Jobs that encountered errors

For each job, you can:
- View the created quiz (if completed)
- Retry failed jobs
- Delete jobs (also removes associated quiz)

## How It Works

### 1. PDF Text Extraction
The plugin attempts to extract text from PDF using multiple methods:
- **pdftotext**: Command-line tool (best results)
- **Smalot PDF Parser**: PHP library (good results)
- **Regex fallback**: Basic extraction (limited support)

### 2. AI Question Generation
Extracted text is sent to OpenRouter API with a carefully crafted prompt that:
- Specifies the number and type of questions
- Requests clear, unambiguous questions
- Includes correct answers and explanations
- Returns structured JSON data

### 3. Quiz Creation
Generated questions are converted to Moodle question formats:
- **Multiple Choice**: 4 options with single correct answer
- **True/False**: Boolean questions
- **Short Answer**: Free text answers with acceptable variations

## Troubleshooting

### "PDF extraction failed" Error

**Problem**: The plugin cannot extract text from your PDF.

**Solutions**:
1. Ensure the PDF contains selectable text (not scanned images)
2. Install `pdftotext` on your server:
   ```bash
   # Ubuntu/Debian
   sudo apt-get install poppler-utils

   # CentOS/RHEL
   sudo yum install poppler-utils

   # macOS
   brew install poppler
   ```
3. Install Smalot PDF Parser via Composer for better support

### "API Error" Messages

**Problem**: OpenRouter API is not responding or returning errors.

**Solutions**:
1. Verify your API key is correct
2. Check your OpenRouter account has available credits
3. Increase the API timeout in settings (default: 60 seconds)
4. Try a different AI model
5. Check OpenRouter status page for outages

### Questions Not Generated

**Problem**: The AI didn't generate the expected number of questions.

**Solutions**:
1. Ensure the PDF has sufficient educational content
2. Try a different question type
3. Check the extracted text quality in job details
4. Retry the job

### Plugin Not Showing in Course

**Problem**: Can't find the PDF Quiz Generator link.

**Solutions**:
1. Verify plugin is installed: **Site Administration > Plugins > Local Plugins**
2. Check you have the `local/pdfquizgen:use` capability
3. Purge Moodle caches: **Site Administration > Development > Purge caches**

## API Models Available

The plugin supports various AI models through OpenRouter:

| Model | Description | Best For |
|-------|-------------|----------|
| `openai/gpt-4o-mini` | Fast and affordable | Quick generation, testing |
| `openai/gpt-4o` | High quality | Production use |
| `anthropic/claude-3.5-sonnet` | Excellent reasoning | Complex content |
| `anthropic/claude-3-haiku` | Fast Claude model | Speed priority |
| `google/gemini-flash-1.5` | Google's fast model | Alternative option |
| `meta-llama/llama-3.1-70b` | Open source | Cost-effective |

## Database Schema

### local_pdfquizgen_jobs
Stores quiz generation jobs:
- `id`: Job ID
- `courseid`: Course ID
- `userid`: User who created the job
- `fileid`: PDF file ID
- `filename`: Original filename
- `status`: pending/processing/completed/failed
- `quizid`: Created quiz ID
- `questioncount`: Number of questions requested
- `questiontype`: Type of questions
- `extracted_text`: Text extracted from PDF
- `api_response`: Raw API response
- `error_message`: Error details if failed
- `timecreated/timemodified/timecompleted`: Timestamps

### local_pdfquizgen_questions
Stores generated questions:
- `id`: Question ID
- `jobid`: Parent job ID
- `questiontext`: Question text
- `questiontype`: Question type
- `options`: JSON-encoded options (MCQ)
- `correctanswer`: Correct answer
- `explanation`: Answer explanation
- `moodle_questionid`: Linked Moodle question ID

### local_pdfquizgen_logs
Activity logs for auditing.

## Development

### File Structure
```
local/pdfquizgen/
├── classes/
│   ├── pdf_extractor.php      # PDF text extraction
│   ├── openrouter_client.php  # OpenRouter API client
│   ├── quiz_generator.php     # Moodle quiz creation
│   └── job_manager.php        # Job management
├── db/
│   ├── access.php             # Capabilities
│   ├── install.xml            # Database schema
│   └── upgrade.php            # Upgrade scripts
├── lang/en/
│   └── local_pdfquizgen.php   # Language strings
├── amd/src/                   # JavaScript (if needed)
├── templates/                 # Mustache templates (if needed)
├── index.php                  # Main interface
├── logs.php                   # Logs viewer
├── lib.php                    # Library functions
├── settings.php               # Admin settings
└── version.php                # Version info
```

### Hooks

The plugin uses these Moodle hooks:
- `local_pdfquizgen_extend_navigation_course`: Adds link to course navigation
- `local_pdfquizgen_extend_settings_navigation`: Adds link to settings menu

### Events

Logged actions:
- `job_created`: New job created
- `job_completed`: Job finished successfully
- `job_failed`: Job failed
- `job_deleted`: Job deleted

## Security Considerations

1. **API Key Storage**: Stored in Moodle config (encrypted in database)
2. **File Access**: Only accesses files within course context
3. **Permissions**: Respects Moodle capability system
4. **Data Retention**: Jobs and logs can be cleaned up periodically
5. **API Calls**: All API calls use HTTPS

## Performance

- PDF extraction happens synchronously (may take a few seconds)
- API calls timeout after configured duration (default: 60s)
- Large PDFs are truncated to configured limit (default: 15000 chars)
- Consider using cron for large batch processing (future feature)

## Future Enhancements

Potential improvements:
- [ ] Asynchronous processing via cron
- [ ] Batch PDF processing
- [ ] Question preview before creating quiz
- [ ] Custom question templates
- [ ] Support for more question types (Matching, Essay)
- [ ] Question difficulty levels
- [ ] Integration with other AI providers
- [ ] Export/import question banks

## License

This plugin is licensed under the GNU GPL v3 or later.

## Support

For issues, questions, or contributions:
- GitHub Issues: [repository-url]/issues
- Email: [your-email]

## Credits

Developed for Moodle by [Your Name/Organization].

Powered by:
- [OpenRouter](https://openrouter.ai/) - AI model aggregation
- [Moodle](https://moodle.org/) - Learning Platform
- [Smalot PDF Parser](https://github.com/smalot/pdfparser) - PDF parsing (optional)
