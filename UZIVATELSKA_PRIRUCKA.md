# MoodleTestGeneratorPlugin - Uživatelská příručka

## Úvod

MoodleTestGeneratorPlugin je plugin pro Moodle, který automaticky vytváří kvízy z vašich výukových materiálů (PDF a Word dokumentů) pomocí umělé inteligence.

---

## Rychlý start

### 1. Přístup k pluginu

1. Přejděte do vašeho kurzu v Moodle
2. V postranním menu najděte **MoodleTestGeneratorPlugin** nebo
3. Přejděte na **Nastavení kurzu > MoodleTestGeneratorPlugin**

### 2. Vytvoření kvízu

1. **Vyberte soubory** - Klikněte na PDF nebo Word dokumenty v seznamu materiálů kurzu
   - Můžete vybrat více souborů najednou (otázky budou generovány ze všech)
   
2. **Nastavte počet otázek** - Zadejte číslo 1-100 (výchozí: 10)

3. **Vyberte typ otázek:**
   - **Výběr z odpovědí** - 4 možnosti, jedna správná
   - **Pravda/Nepravda** - Ano/Ne otázky
   - **Krátká odpověď** - Textová odpověď
   - **Smíšené** - Kombinace všech typů

4. **Klikněte na "Vytvořit kvíz"**

5. **Počkejte na zpracování** - Obvykle 30-60 sekund
   - Uvidíte indikátor průběhu
   - Po dokončení se zobrazí odkaz na kvíz

---

## Hlavní obrazovka

### Statistiky

V horní části vidíte statistiky:

| Položka | Popis |
|---------|-------|
| **Celkem** | Všechny vytvořené úlohy |
| **Zpracovává se** | Aktuálně generované kvízy |
| **Dokončeno** | Úspěšně vytvořené kvízy |
| **Selhalo** | Neúspěšné pokusy |

### Seznam úloh

Tabulka zobrazuje všechny úlohy s možnostmi:

- **Zobrazit kvíz** - Otevře vytvořený kvíz
- **Opakovat** - Znovu spustí neúspěšnou úlohu
- **Smazat** - Odstraní úlohu (i kvíz, pokud existuje)

---

## Výběr více souborů

Plugin podporuje generování kvízu z více dokumentů současně:

1. Klikněte na první soubor (zvýrazní se)
2. Klikněte na další soubory (přidají se do výběru)
3. Kliknutím na vybraný soubor ho odstraníte z výběru
4. Počet otázek se rozdělí proporcionálně podle délky textu

**Příklad:** Pokud vyberete 2 dokumenty a 10 otázek:
- Dokument A (60% textu) → 6 otázek
- Dokument B (40% textu) → 4 otázky

---

## Typy otázek

### Výběr z odpovědí (Multiple Choice)
```
Otázka: Jaký je hlavní účel...?
a) Odpověď A
b) Odpověď B (správná)
c) Odpověď C
d) Odpověď D
```

### Pravda/Nepravda (True/False)
```
Tvrzení: Software maintenance zahrnuje pouze opravy chyb.
○ Pravda
● Nepravda (správná)
```

### Krátká odpověď (Short Answer)
```
Otázka: Jak se nazývá proces...?
Odpověď: [____________]
```

---

## Podporované formáty souborů

| Formát | Přípona | Poznámky |
|--------|---------|----------|
| PDF | .pdf | Musí obsahovat textovou vrstvu |
| Word (nový) | .docx | Plně podporován |
| Word (starší) | .doc | Základní podpora |

### Omezení PDF
- Skenované dokumenty bez OCR neobsahují text
- Některé PDF s komplexním formátováním mohou mít problémy
- Maximální velikost: 50 MB (konfigurovatelné)

---

## Často kladené otázky

### Jak dlouho trvá generování?
Typicky 30-60 sekund, závisí na:
- Délce dokumentu
- Počtu otázek
- Vytížení AI služby

### Mohu upravit vygenerované otázky?
Ano! Po vytvoření kvízu můžete:
1. Přejít do kvízu
2. Kliknout na "Upravit kvíz"
3. Upravit jednotlivé otázky

### Proč se kvíz nevytvořil?
Možné příčiny:
- PDF neobsahuje textový obsah
- Nedostatečný kredit na OpenRouter
- Timeout při komunikaci s API
- Obsah není vhodný pro generování otázek

### V jakém jazyce budou otázky?
Otázky jsou generovány ve stejném jazyce, v jakém je napsán zdrojový dokument. AI automaticky detekuje jazyk obsahu.

### Kolik stojí generování?
Plugin používá OpenRouter API, které je placené. Ceny závisí na zvoleném modelu:
- GPT-4o Mini: ~$0.15/1M tokenů
- Claude 3.5 Sonnet: ~$3/1M tokenů

---

## Tipy pro nejlepší výsledky

1. **Kvalitní zdroj** - Používejte dokumenty s jasným vzdělávacím obsahem
2. **Strukturovaný text** - Dokumenty s nadpisy a odrážkami fungují lépe
3. **Přiměřená délka** - Příliš krátké texty (< 500 znaků) nemusí generovat kvalitní otázky
4. **Vhodný typ** - Pro faktické informace volte Multiple Choice, pro koncepty True/False
5. **Kontrola výsledků** - Vždy zkontrolujte vygenerované otázky před použitím

---

## Řešení problémů

### "Extrakce textu selhala"
- Zkontrolujte, že PDF obsahuje text (ne jen obrázky)
- Zkuste jiný dokument

### "Chyba API"
- Zkontrolujte internetové připojení
- Kontaktujte administrátora pro ověření API klíče

### Kvíz má málo otázek
- Zdrojový text může být příliš krátký
- Zkuste zvýšit počet požadovaných otázek
- Přidejte další soubory

### Otázky nejsou relevantní
- Zkuste jiný AI model v nastavení
- Ujistěte se, že dokument obsahuje vzdělávací obsah

---

## Kontakt a podpora

Pro technické problémy kontaktujte administrátora Moodle nebo:
- Vytvořte issue na GitHub repozitáři
- Email: [kontakt administrátora]

---

*MoodleTestGeneratorPlugin v1.6.0 - Vytvořeno pro Moodle 4.1+*

