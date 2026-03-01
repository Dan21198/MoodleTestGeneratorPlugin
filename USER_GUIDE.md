#  MoodleTestGeneratorPlugin - User Guide

## Quick Start

### 1. Access the Plugin
- Go to your course in Moodle
- In the course menu, find **MoodleTestGeneratorPlugin**
- Or navigate via: **Course Administration > MoodleTestGeneratorPlugin**

### 2. Generate Your First Quiz
1. **Select files** - Click on one or more PDF/Word documents
2. **Set question count** - Enter a number (1-100)
3. **Choose question type** - Multiple choice, True/False, Short answer, or Mixed
4. **Click Generate Quiz** - Wait for processing to complete
5. **Done!** - Click "View Quiz" to see your new quiz

---

## Main Interface

### Statistics Panel

At the top of the page, you'll see statistics about your quiz generation jobs:

| Statistic | Description |
|-----------|-------------|
| **Total** | All jobs you have created |
| **Processing** | Currently being processed |
| **Completed** | Successfully created quizzes |
| **Failed** | Jobs that encountered errors |

### File Selection

The left panel displays all available documents in your course:

- **Supported formats:** PDF (`.pdf`), Word (`.docx`, `.doc`)
- **Click to select:** Files are highlighted when selected
- **Multiple selection:** Select as many files as needed
- **File info:** Shows file name and size

### Generation Form

| Option | Description |
|--------|-------------|
| **Number of Questions** | How many questions to generate (1-100) |
| **Question Type** | Type of questions to create |

### Job List

Recent jobs are displayed below with:
- **Status indicator** (Processing/Completed/Failed)
- **File name** and **number of questions**
- **Creation date**
- **Actions:** View, Retry, Delete

---

## Selecting Multiple Files

### How It Works

1. Click on any PDF or Word document to select it
2. Selected files appear highlighted
3. Click again to deselect
4. A "Selected files" counter shows how many are chosen

### Question Distribution

When multiple files are selected, questions are distributed automatically based on content length:

**Example:**
- File A: 8,000 characters (80% of content)
- File B: 2,000 characters (20% of content)
- Requested: 10 questions

**Result:**
- File A: 8 questions
- File B: 2 questions

Each file with sufficient content (>500 characters) gets at least 1 question.

---

## Question Types

### Multiple Choice
- 4 answer options labeled A, B, C, D
- One correct answer
- Explanation provided for feedback
- Options are shuffled when displayed

**Example:**
> What is the primary purpose of software testing?
> - A) To find bugs
> - B) To improve performance
> - C) To verify requirements are met ✓
> - D) To document code

### True/False
- Statement that is either true or false
- Clear explanation of why
- Good for factual content

**Example:**
> TCP is a connectionless protocol.
> - True
> - False ✓

### Short Answer
- Student types their answer
- Multiple acceptable answers supported
- Case-insensitive matching

**Example:**
> What protocol is used for secure web browsing?
> 
> Acceptable answers: HTTPS, https, HTTP Secure

### Mixed
- Combination of all question types
- AI selects the most appropriate type for each question
- Best variety for comprehensive assessment

---

## Supported File Formats

### PDF Files
| Feature | Support |
|---------|---------|
| Text-based PDFs |  Full support |
| Searchable PDFs |  Full support |
| Scanned images |  Not supported |
| Password protected |  Not supported |

**Tip:** If your PDF is scanned, apply OCR (Optical Character Recognition) first.

### Word Documents
| Format | Support |
|--------|---------|
| .docx (Modern) |  Full support |
| .doc (Legacy) | Basic support |

---

## Frequently Asked Questions

### Why did my job fail?

Common reasons:
1. **PDF has no text** - The document might be scanned without OCR
2. **API error** - Check if you have OpenRouter credits
3. **Content too short** - Document needs more educational content
4. **Timeout** - Large documents may take longer; admin can increase timeout

### Can I edit the generated quiz?

Yes! After generation, the quiz is a normal Moodle quiz. You can:
- Edit, add, or remove questions
- Change quiz settings
- Reorder questions
- Modify answers

### What language are questions in?

Questions are generated in the **same language as your source document**. Upload documents in your preferred language.

### How many questions can I generate?

You can request between 1 and 100 questions per job. The actual number depends on:
- Content length and quality
- AI model capabilities
- Available OpenRouter credits

### What happens if I delete a job?

Deleting a job will:
- Remove the job record
- Delete the associated quiz (if created)
- Clear related questions from the database

---

## Tips for Best Results

### Document Quality
- Use well-structured documents with clear sections
- Include educational content (definitions, concepts, facts)
- Use proper formatting (headings, paragraphs)
- Avoid documents with mostly images or tables
- Avoid very short documents (<500 characters)

### Question Settings
- **Start small:** Try 5 questions first to check quality
- **Use mixed type:** Get variety without choosing manually
- **Multiple files:** Combine related documents for broader coverage

### After Generation
- **Review questions** before sharing with students
- **Check answers** - AI is good but not perfect
- **Edit as needed** - Modify wording for clarity

---

## Troubleshooting

### "Processing" status stuck

**Wait a moment** - Generation can take 30-60 seconds depending on:
- Document size
- Number of questions
- API response time

If stuck for more than 2 minutes:
1. Refresh the page
2. Check the job status
3. Retry if failed

### No files appear in the list

Check that:
- You have PDF or Word files in your course
- Files are in a visible section
- You have permission to view course content

### Questions don't match content

This can happen when:
- PDF text extraction failed (check logs)
- Document has poor formatting
- Content is too technical or specialized

**Solution:** Try a different AI model in plugin settings.

### Error: "API Error"

| Error Code | Meaning | Solution |
|------------|---------|----------|
| 401 | Invalid API key | Contact administrator |
| 402 | No credits | Top up OpenRouter account |
| 429 | Rate limited | Wait and retry |
| 500 | Server error | Retry later |

---

## Getting Help

### Log Viewer
Access detailed logs at: **Course > MoodleTestGeneratorPlugin > View Logs**

Logs show:
- Job creation
- Text extraction results
- API calls and responses
- Quiz creation details
- Errors with details

### Contact Administrator

If you encounter persistent issues:
1. Note the job ID and error message
2. Take a screenshot
3. Contact your Moodle administrator

---

## Keyboard Shortcuts

| Action | Shortcut |
|--------|----------|
| Submit form | Enter |
| Clear selection | Escape |

---

## Privacy Notes

- Your documents are processed temporarily and not stored permanently
- Extracted text is sent to OpenRouter API for question generation
- Generated questions are stored in Moodle's database
- Job logs are retained for a limited time (typically 30 days)

---

## Version Information

Current version: **1.6.0**

Features in this version:
- Multi-file selection
- Word document support
- Real-time progress tracking
- Smart question distribution
- Multiple AI model options

