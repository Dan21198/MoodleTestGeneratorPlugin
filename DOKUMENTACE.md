# MoodleTestGeneratorPlugin - Technická dokumentace

## Přehled pluginu

**Název:** MoodleTestGeneratorPlugin  
**Komponenta:** `local_pdfquizgen`  
**Verze:** 1.6.0  
**Autor:** Daniel Hořejší  
**Licence:** GNU GPL v3  
**Minimální verze Moodle:** 4.1 (2022112800)

### Účel

MoodleTestGeneratorPlugin je lokální plugin pro Moodle, který automaticky generuje kvízy z PDF a Word dokumentů pomocí AI (umělé inteligence) prostřednictvím OpenRouter API. Plugin umožňuje pedagogům rychle vytvářet testové otázky z výukových materiálů bez nutnosti manuálního zadávání.

---

## Architektura systému

### Vysokoúrovňový přehled

```
┌─────────────────────────────────────────────────────────────────────┐
│                         UŽIVATELSKÉ ROZHRANÍ                        │
│                           (index.php)                               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────────────────┐ │
│  │ Výběr       │───▶│ Job Manager │───▶│ External API            │ │
│  │ souborů     │    │             │    │ (process_job.php)       │ │
│  └─────────────┘    └──────┬──────┘    └────────────┬────────────┘ │
│                            │                        │               │
│                            ▼                        ▼               │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │                    ZPRACOVÁNÍ SOUBORŮ                           ││
│  │  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ ││
│  │  │ File Extractor  │─▶│ PDF Extractor   │  │ Word Extractor  │ ││
│  │  │ (koordinátor)   │  │ (pdftotext,     │  │ (DOCX/DOC)      │ ││
│  │  └─────────────────┘  │ Smalot, regex)  │  └─────────────────┘ ││
│  │                       └─────────────────┘                       ││
│  └─────────────────────────────────────────────────────────────────┘│
│                            │                                        │
│                            ▼                                        │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │                    GENEROVÁNÍ OTÁZEK                            ││
│  │  ┌─────────────────┐  ┌─────────────────────────────────────┐  ││
│  │  │ OpenRouter      │─▶│ AI Modely (GPT-4o, Claude, Gemini)  │  ││
│  │  │ Client          │  └─────────────────────────────────────┘  ││
│  │  └─────────────────┘                                            ││
│  └─────────────────────────────────────────────────────────────────┘│
│                            │                                        │
│                            ▼                                        │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │                    VYTVOŘENÍ KVÍZU                              ││
│  │  ┌─────────────────┐  ┌─────────────────────────────────────┐  ││
│  │  │ Quiz Generator  │─▶│ Question Type Factory               │  ││
│  │  │ (orchestrace)   │  │  ├─ multichoice_question            │  ││
│  │  └─────────────────┘  │  ├─ truefalse_question              │  ││
│  │                       │  └─ shortanswer_question            │  ││
│  │                       └─────────────────────────────────────┘  ││
│  └─────────────────────────────────────────────────────────────────┘│
│                            │                                        │
│                            ▼                                        │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │                    MOODLE DATABÁZE                              ││
│  │  ├─ quiz, quiz_slots                                            ││
│  │  ├─ question, question_versions, question_bank_entries          ││
│  │  ├─ question_answers, qtype_*_options                           ││
│  │  └─ local_pdfquizgen_jobs, _questions, _logs                    ││
│  └─────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────┘
```

---

## Struktura souborů

```
local/pdfquizgen/
├── amd/                           # AMD JavaScript moduly
│   ├── src/
│   │   └── job_processor.js       # Asynchronní zpracování úloh
│   └── build/
│       └── job_processor.min.js   # Minifikovaná verze
│
├── classes/                       # PHP třídy (PSR-4 autoloading)
│   ├── external/                  # Moodle External API
│   │   ├── process_job.php        # AJAX endpoint pro zpracování
│   │   └── get_job_status.php     # AJAX endpoint pro stav úlohy
│   │
│   ├── question/                  # Továrna na typy otázek
│   │   ├── question_type_factory.php   # Factory pattern
│   │   ├── question_type_base.php      # Abstraktní třída
│   │   ├── multichoice_question.php    # Multiple choice handler
│   │   ├── truefalse_question.php      # True/false handler
│   │   ├── shortanswer_question.php    # Short answer handler
│   │   └── question_helper.php         # Pomocné funkce
│   │
│   ├── task/                      # Naplánované úlohy
│   │   └── cleanup_old_data.php   # Čištění starých dat
│   │
│   ├── util/                      # Utility třídy
│   │
│   ├── file_extractor.php         # Koordinátor extrakce souborů
│   ├── pdf_extractor.php          # Extrakce textu z PDF
│   ├── word_extractor.php         # Extrakce textu z Word
│   ├── openrouter_client.php      # OpenRouter API klient
│   ├── quiz_generator.php         # Generátor kvízů
│   └── job_manager.php            # Správa úloh
│
├── db/                            # Databázové definice
│   ├── access.php                 # Definice oprávnění
│   ├── install.xml                # Schéma tabulek
│   ├── services.php               # External services
│   └── upgrade.php                # Migrace databáze
│
├── lang/                          # Jazykové soubory
│   └── en/
│       └── local_pdfquizgen.php   # Anglické překlady
│
├── cli/                           # CLI skripty
│
├── index.php                      # Hlavní uživatelské rozhraní
├── logs.php                       # Zobrazení logů
├── lib.php                        # Knihovní funkce a hooks
├── settings.php                   # Administrátorská nastavení
├── version.php                    # Informace o verzi
├── thirdpartylibs.xml            # Deklarace knihoven třetích stran
└── README.md                      # Dokumentace (anglicky)
```

---

## Komponenty systému

### 1. Job Manager (`job_manager.php`)

**Účel:** Centrální správa životního cyklu úloh generování kvízů.

**Hlavní metody:**
- `create_job()` - Vytvoření nové úlohy
- `process_job()` - Zpracování úlohy (extrakce, generování, vytvoření kvízu)
- `complete_job()` - Dokončení úlohy
- `fail_job()` - Označení úlohy jako neúspěšné
- `delete_job()` - Smazání úlohy
- `get_job()` - Získání detailů úlohy
- `get_jobs_for_course()` - Seznam úloh pro kurz
- `get_job_statistics()` - Statistiky úloh

**Stavy úloh:**
- `processing` - Úloha se zpracovává
- `completed` - Úloha úspěšně dokončena
- `failed` - Úloha selhala

### 2. File Extractor (`file_extractor.php`)

**Účel:** Koordinátor extrakce textu z různých typů souborů.

**Podporované formáty:**
- PDF (`.pdf`)
- Word dokumenty (`.docx`, `.doc`)

**Logika:**
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

**Účel:** Extrakce textu z PDF souborů pomocí několika metod.

**Metody extrakce (v pořadí priority):**

1. **pdftotext** (nejlepší výsledky)
   - Systémový příkaz z balíku `poppler-utils`
   - Vyžaduje instalaci na serveru
   
2. **Smalot PDF Parser** (dobré výsledky)
   - PHP knihovna instalovaná přes Composer
   - `composer require smalot/pdfparser`
   
3. **Stream extraction** (základní)
   - Dekomprese FlateDecode/ASCIIHexDecode streamů
   - Regex parsování textu
   
4. **Basic extraction** (fallback)
   - Jednoduchá extrakce bez dekomprese

**Čištění textu:**
- Konverze kódování do UTF-8
- Odstranění neplatných znaků
- Normalizace řádků
- Odstranění přebytečných mezer

### 4. Word Extractor (`word_extractor.php`)

**Účel:** Extrakce textu z Word dokumentů.

**DOCX formát:**
- Parsování `word/document.xml` z ZIP archivu
- Extrakce textu z `<w:t>` elementů

**DOC formát (starší):**
- Binární parsování
- Regex extrakce textu

### 5. OpenRouter Client (`openrouter_client.php`)

**Účel:** Komunikace s OpenRouter API pro generování otázek.

**Konfigurace:**
- API klíč
- Model (GPT-4o, Claude, Gemini, atd.)
- Timeout
- Max tokens

**Podporované modely:**
| Model | Popis |
|-------|-------|
| `openai/gpt-4o-mini` | Rychlý a cenově dostupný |
| `openai/gpt-4o` | Nejvyšší kvalita |
| `anthropic/claude-3.5-sonnet` | Výborná kvalita |
| `anthropic/claude-3-haiku` | Rychlý Claude |
| `google/gemini-2.5-pro` | Pro dlouhý obsah |
| `google/gemini-2.5-flash` | Rychlý Google |
| `meta-llama/llama-3.1-70b-instruct` | Open source |
| `mistralai/mistral-large-2512` | Evropská alternativa |

**Prompt struktura:**
```php
$systemPrompt = "You are an expert educational content creator...";
$userPrompt = "Based on the following educational content, generate {$count} 
               {$type} questions in the same language as the content...";
```

**Zpracování odpovědi:**
- Parsování JSON z markdown bloků
- Validace struktury otázek
- Ošetření chyb API

### 6. Quiz Generator (`quiz_generator.php`)

**Účel:** Orchestrace vytváření kvízu v Moodle.

**Kroky vytvoření:**
1. Validace vstupních otázek
2. Získání/vytvoření kategorie otázek
3. Vytvoření quiz aktivity
4. Vytvoření course module
5. Vytvoření jednotlivých otázek (delegováno na question handlers)
6. Přidání otázek do kvízu (quiz_slots)
7. Výpočet sumgrades

### 7. Question Type Factory (`question_type_factory.php`)

**Účel:** Factory pattern pro vytváření správných handlers podle typu otázky.

**Podporované typy:**
- `multichoice` - Otázka s výběrem odpovědi
- `truefalse` - Pravda/Nepravda
- `shortanswer` - Krátká odpověď
- `mixed` - Kombinace typů

**Příklad použití:**
```php
$handler = question_type_factory::create('multichoice', $categoryid, $userid);
$questionid = $handler->create($questiondata);
```

---

## Databázové schéma

### Tabulka: `local_pdfquizgen_jobs`

Ukládá informace o úlohách generování kvízů.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | BIGINT | Primární klíč |
| `courseid` | BIGINT | ID kurzu |
| `userid` | BIGINT | ID uživatele |
| `fileid` | BIGINT | ID souboru (legacy) |
| `fileids` | TEXT | JSON pole ID souborů |
| `filename` | VARCHAR(255) | Název souboru |
| `status` | VARCHAR(20) | Stav úlohy |
| `quizid` | BIGINT | ID vytvořeného kvízu |
| `questioncount` | INT | Počet požadovaných otázek |
| `questiontype` | VARCHAR(20) | Typ otázek |
| `extracted_text` | LONGTEXT | Extrahovaný text |
| `api_response` | LONGTEXT | Odpověď API |
| `error_message` | TEXT | Chybová zpráva |
| `timecreated` | BIGINT | Čas vytvoření |
| `timemodified` | BIGINT | Čas poslední změny |
| `timecompleted` | BIGINT | Čas dokončení |

### Tabulka: `local_pdfquizgen_questions`

Ukládá vygenerované otázky.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | BIGINT | Primární klíč |
| `jobid` | BIGINT | ID rodičovské úlohy |
| `questiontext` | TEXT | Text otázky |
| `questiontype` | VARCHAR(20) | Typ otázky |
| `options` | TEXT | JSON možností (MCQ) |
| `correctanswer` | TEXT | Správná odpověď |
| `explanation` | TEXT | Vysvětlení odpovědi |
| `moodle_questionid` | BIGINT | ID Moodle otázky |
| `timecreated` | BIGINT | Čas vytvoření |

### Tabulka: `local_pdfquizgen_logs`

Logovací tabulka pro audit.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | BIGINT | Primární klíč |
| `jobid` | BIGINT | ID úlohy |
| `courseid` | BIGINT | ID kurzu |
| `userid` | BIGINT | ID uživatele |
| `action` | VARCHAR(50) | Typ akce |
| `details` | TEXT | Detaily |
| `timecreated` | BIGINT | Čas vytvoření |

---

## Datový tok vytvoření kvízu

### Sekvenční diagram

```
Uživatel          index.php       JobManager      FileExtractor     OpenRouterClient    QuizGenerator
    │                 │               │                │                   │                  │
    │ 1. Výběr souborů│               │                │                   │                  │
    │────────────────▶│               │                │                   │                  │
    │                 │               │                │                   │                  │
    │ 2. Odeslat form │               │                │                   │                  │
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
    │                 │               │ 9. Otázky JSON │                   │                  │
    │                 │               │◀───────────────────────────────────│                  │
    │                 │               │                │                   │                  │
    │                 │               │ 10. create_quiz()                                     │
    │                 │               │────────────────────────────────────────────────────── ▶│
    │                 │               │                │                   │                  │
    │                 │               │ 11. Quiz ID, CMID                                     │
    │                 │               │◀──────────────────────────────────────────────────────│
    │                 │               │                │                   │                  │
    │ 12. Výsledek    │               │                │                   │                  │
    │◀────────────────│               │                │                   │                  │
```

---

## External API

### process_job

**Endpoint:** `local_pdfquizgen_process_job`

**Parametry:**
- `jobid` (int) - ID úlohy ke zpracování

**Návratová hodnota:**
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

**Parametry:**
- `jobid` (int) - ID úlohy

**Návratová hodnota:**
```json
{
    "status": "completed",
    "quizid": 42,
    "cmid": 15,
    "error": ""
}
```

---

## Konfigurace

### Administrátorská nastavení

Cesta: **Site Administration > Plugins > Local Plugins > MoodleTestGeneratorPlugin**

| Nastavení | Výchozí | Popis |
|-----------|---------|-------|
| `openrouter_api_key` | - | API klíč OpenRouter |
| `openrouter_model` | gpt-4o-mini | Vybraný AI model |
| `openrouter_model_custom` | - | Vlastní model (pokud "other") |
| `openrouter_timeout` | 60 | Timeout API v sekundách |
| `max_tokens` | 2000 | Max tokens pro odpověď |
| `default_question_count` | 10 | Výchozí počet otázek |
| `default_question_type` | multichoice | Výchozí typ otázek |
| `max_pdf_size` | 50 | Max velikost PDF v MB |
| `max_text_length` | 15000 | Max délka extrahovaného textu |
| `enable_logging` | 1 | Povolit logování |
| `max_retries` | 3 | Počet pokusů při chybě |

### Oprávnění

**Capability:** `local/pdfquizgen:use`

**Výchozí role:**
- `editingteacher`
- `manager`

---

## Bezpečnostní aspekty

1. **Úložiště API klíče:** Uložen v Moodle konfiguraci (šifrovaná databáze)
2. **Přístup k souborům:** Pouze soubory v kontextu kurzu
3. **Oprávnění:** Respektuje systém capabilities Moodle
4. **Validace vstupů:** Všechny vstupy jsou validovány
5. **HTTPS:** Všechny API volání používají HTTPS
6. **Session kontrola:** Ověření sesskey při každé akci

---

## Výkon a limity

- **PDF extrakce:** Synchronní zpracování (několik sekund)
- **API timeout:** Konfigurovatelný (výchozí 60s)
- **Velké PDF:** Truncovány na max_text_length
- **Multi-file:** Distribuce otázek podle délky textu

### Vzorec distribuce otázek

```
questions_per_file = (file_text_length / total_text_length) * total_questions
```

S minimem 1 otázky pro soubory > 500 znaků.

---

## Řešení problémů

### Běžné chyby

| Chyba | Příčina | Řešení |
|-------|---------|--------|
| PDF extraction failed | PDF neobsahuje text | Použít OCR nebo jiný dokument |
| API Error 402 | Nedostatek kreditů | Dobít OpenRouter účet |
| API Error 401 | Neplatný API klíč | Zkontrolovat API klíč |
| JSON parse error | AI vrátil neplatný formát | Zkusit jiný model |
| Undefined array key -1 | Chyba v quiz slots | Purge cache |

### Debugging

1. **Povolit logování** v nastavení
2. **Zobrazit logy:** `/local/pdfquizgen/logs.php`
3. **Moodle debug:** Nastavit `$CFG->debug = DEBUG_DEVELOPER`

---