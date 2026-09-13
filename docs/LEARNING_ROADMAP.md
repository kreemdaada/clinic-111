# Lern-Roadmap — Software Schritt für Schritt verstehen
 Es rechnet nur Geld: Was wurde bezahlt? Was kostet das Labor? Was bekommt der Arzt?

---

## Phase 0 — Das Alltagsbild (5 Minuten)

Stell dir einen normalen Tag in der Klinik vor:

| Real life | Im System |
|---|---|
| Buchhalterin bekommt Excel „Daily Report Januar“ | Datei-Upload |
| Zeile: Patient bezahlt 1000 AED bar, 200 Visa, Behandlung „ZIR x 2“ | Eine `daily_work_row` |
| „1000 bar“ + „200 Karte“ | Zwei `payments` |
| „ZIR x 2“ = 2 Zirkonkronen | Ein `work_item` (Code ZIR, Menge 2) |
| Labor rechnet 400 AED pro Krone für Dr. Name2 | `lab_job` = 2 × 400 = **800 AED JOB** |
| Am Monatsende: Wie viel hat Dr. Name2 verdient? | `MonthlyIncomeCalculationService` |

**Patientenname steht nur kurz im Excel — wird nicht dauerhaft gespeichert.** Stattdessen ein anonymer Hash für Nachvollziehbarkeit.

---

## Phase 1 — Wo fängt der Code an? (Einstieg)

Lies in **dieser Reihenfolge** — nicht alles auf einmal.

### Stufe 1: Die Tür (Routes + Controller)

| Datei | Warum zuerst? |
|---|---|
| `routes/api.php` | Welche URLs gibt es? (Import, Report anzeigen, Monatslohn) |
| `app/Http/Controllers/Api/DailyReportController.php` | Was passiert beim Excel-Upload? Ruft nur Services auf — **wenig Logik** |

**Life-Beispiel:** Du rufst `POST /api/daily-reports/import` auf → Controller ist wie die Rezeption: nimmt die Datei entgegen und ruft den Fachmann (`DailyReportImportService`).

### Stufe 2: Der Dirigent (Import-Orchestrierung)

| Datei | Rolle |
|---|---|
| `app/Services/Import/DailyReportImportService.php` | **Wichtigste Datei zum Verstehen des Gesamtflows** |

Lies diese Datei von oben nach unten und folge dem Ablauf:

1. Excel speichern  
2. Excel lesen  
3. Zeilen in DB schreiben  
4. Behandlungen prüfen + speichern  
5. Labor-Kosten rechnen  
6. Status setzen (`calculated` oder `needs_review`)

**Life-Beispiel:** Wie eine erfahrene Buchhalterin am Schreibtisch: erst Liste eintragen, dann Behandlungen prüfen, dann Lab-Rechnung addieren, am Ende grüner Haken oder „bitte nochmal prüfen“.

### Stufe 3: Die Fachleute (ein Service = eine Aufgabe)

| Datei | Alltags-Aufgabe |
|---|---|
| `ExcelDailyReportParser.php` | Excel öffnen, Zeilen aus Tab „15“ lesen |
| `PaymentCalculationService.php` | „1000 bar + 500 USD + 200 Visa = wie viel AED total?“ |
| `TreatmentParserService.php` | Text „ZIR x 2 + POST x 1“ verstehen |
| `TreatmentImportValidationService.php` | Prüfen: ist der Text korrekt? Warnung bei „zircon 2“ |
| `LabJobCalculationService.php` | Labor-Rechnung pro Krone/Prothese berechnen |
| `DoctorsIncomeExcelExportService.php` | Original-Income-Excel für Dr. Name1/Name2 füllen |

---

## Phase 2 — Daten verstehen (Models + DB)

### Die wichtigsten Tabellen (Models)

| Model / Tabelle | Life-Beispiel |
|---|---|
| `Doctor` | Dr. Name2 — 35 % Provision, eigenes Labor |
| `Treatment` | Code `ZIR` = Zirkonkrone, Flag `has_lab_cost = true` |
| `LabPrice` | ZIR kostet Dr. Name2 400 AED beim Labor |
| `DailyReport` | „Import Januar 2026“ — ein Container pro Monats-Excel |
| `DailyWorkRow` | Eine Zeile im Excel = ein Patientenbesuch / eine Zahlung |
| `Payment` | 1000 AED bar — ein Teil der Zahlung |
| `WorkItem` | „ZIR × 2“ — strukturierte Behandlung |
| `LabJob` | JOB-Spalte: 800 AED Laborkosten für diese 2 Kronen |
| `DailyReportImportWarning` | „Zeile 25: Invalid format. Use ZIR x 2“ |

**Dateien:**

```
app/Models/DailyReport.php
app/Models/DailyWorkRow.php
app/Models/WorkItem.php
app/Models/LabJob.php
app/Models/Doctor.php
app/Models/Treatment.php
```

**Doku:** `docs/DATABASE_SCHEMA.md` — nachschlagen wenn du ein Feld nicht verstehst.

---

## Phase 3 — Der Import-Pipeline (Herzstück)

### Schritt-für-Schritt mit einem konkreten Excel-Zeile

**Excel Zeile 25, Tag 15, Dr. Name2:**

```
Patient: (wird gelesen, nicht gespeichert)
Behandlung: ZIR x 2 + CF x 3
DHS: 2000   USD: 0   Visa: 500
```

```
Schritt 1 — ExcelDailyReportParser
  → Liest Zellen, findet Doctor „Dr. Name2“, treatment_text, Beträge
  → Gibt Array zurück (patient_name nur im RAM)

Schritt 2 — DailyReportImportService::createWorkRowFromParsedData
  → Hash vom Patienten → patient_reference_hash
  → raw_data_json ohne Namen
  → paid_total_aed = 2000 + 500 = 2500 AED

Schritt 3 — TreatmentImportValidationService
  → „ZIR x 2“ ✓ → work_item ZIR qty 2
  → „CF x 3“ ✓ → work_item CF qty 3 (kein Lab Job)
  → Wenn stattdessen „zircon 2“ → Warning, kein work_item dafür

Schritt 4 — LabJobCalculationService
  → Nur ZIR hat has_lab_cost
  → Dr. Name2 + ZIR → 400 AED/Stück
  → lab_job total = 800 AED

Schritt 5 — Status
  → Keine Warnings → calculated
  → Mit Warnings → needs_review
```

**Visual:**

```
Excel-Zeile
    ↓
daily_work_row + payments
    ↓
work_items (ZIR, CF, REMOV, …)
    ↓
lab_jobs (nur MC, ZIR, REMOV, POST, …)
    ↓
Monats-Excel Export + Monatslohn
```

---

## Phase 4 — Geld-Regeln (ohne Code lesen)

Lies **`docs/PROJECT_OVERVIEW.md`** und **`docs/WORKFLOWS.md`** — Überblick über die Buchhaltungslogik.

| Begriff | Bedeutung | Beispiel |
|---|---|---|
| **TOTAL** | Eingezahltes Geld | 2500 AED vom Patienten |
| **JOB** | Labor-Kosten | 800 AED für 2 ZIR |
| **NET** | TOTAL − JOB | 2500 − 800 = 1700 AED |
| **Arzt-Einkommen** | % von NET (bei Dr. Name2 35 %) | 1700 × 35 % = 595 AED |

**Wichtig:** `REMOV` (herausnehmbarer Zahn) hat **Lab Cost 100 AED** — zählt in JOB und in Treatment-Spalte.

---

## Phase 5 — Mit den Händen lernen (praktisch)

### Übung 1: Tests lesen (beste Erklärer)

| Test | Was du lernst |
|---|---|
| `tests/Unit/PaymentCalculationServiceTest.php` | Wie TOTAL gerechnet wird |
| `tests/Unit/TreatmentParserServiceTest.php` | Wie „ZIR x 2“ gelesen wird |
| `tests/Unit/TreatmentImportValidationServiceTest.php` | Warnings bei falschem Format |
| `tests/Unit/LabJobCalculationServiceTest.php` | ZIR × 4 Name2 = 1600 AED |
| `tests/Unit/NonLabTreatmentJobTest.php` | CF bekommt work_item, aber kein lab_job |

```bash
php artisan test --filter=PaymentCalculationServiceTest
```

Wenn ein Test grün ist, verstehst du **eine** Regel zu 100 %.

### Übung 2: Einmal importieren (Web)

1. `./bin/serve`
2. Login `accountant@clinic.test` / `password`
3. Excel hochladen
4. `/logs` und `/imports/{id}` anschauen
5. API: `GET /api/daily-reports/{id}/validation-summary`

### Übung 3: Eine Zeile im Debugger nachverfolgen

Setze in `DailyReportImportService.php` Zeile ~97 ein `dump($parsedRow)` (lokal) und importiere eine kleine Datei. Du siehst genau, was der Parser liefert.

---

## Phase 6 — Datei-Priorität (Cheatsheet)

Wenn du **nur 10 Dateien** kennen willst:

| Prio | Datei | Ein Satz |
|:---:|---|---|
| 1 | `DailyReportImportService.php` | Gesamter Import-Ablauf |
| 2 | `ExcelDailyReportParser.php` | Excel → Arrays |
| 3 | `TreatmentImportValidationService.php` | Warnungen + work_items |
| 4 | `TreatmentParserService.php` | Text → Code + Menge |
| 5 | `PaymentCalculationService.php` | Bar + USD + Visa = TOTAL |
| 6 | `LabJobCalculationService.php` | JOB berechnen |
| 7 | `LabPriceResolver.php` | Welcher Preis für welchen Arzt? |
| 8 | `DoctorsIncomeExcelExportService.php` | Monats-Excel für Ärzte |
| 9 | `MonthlyIncomeCalculationService.php` | Monatslohn |
| 10 | `config/accounting.php` | USD-Kurs, Upload, HMAC-Key |

Alles andere ist **Hilfsmittel** (Logs, Export-Profile, Privacy, API-Format).

---

## Phase 7 — Typische Fragen neuer Entwickler

### „Wo ändere ich den ZIR-Preis für Dr. Name2?“

→ `database/seeders/LabPriceSeeder.php` (oder später Admin-UI / DB-Tabelle `lab_prices`)  
→ Logik: `LabPriceResolver.php`

### „Warum wird CF nicht in Spalte G (JOB) geschrieben?“

→ CF hat `has_lab_cost = false` — nur `work_item`, kein `lab_job`.  
→ Siehe `TreatmentSeeder.php` und `LabJobCalculationService.php` Zeile „Skip if not has_lab_cost“.

### „Warum steht needs_review statt calculated?“

→ Mindestens eine Warning in `daily_report_import_warnings`.  
→ API: `validation-summary` zeigt welche Excel-Zeile.

### „Wo sind Patientennamen?“

→ Nirgends in der DB/API. Nur Hash. Siehe `PatientReferenceHasher.php`.

---

## Phase 8 — Laravel-Grundlagen die du brauchst

Du musst **nicht** alles Laravel können. Reicht:

| Konzept | Im Projekt |
|---|---|
| **Route → Controller → Service** | `api.php` → `DailyReportController` → `DailyReportImportService` |
| **Eloquent Model** | `DailyWorkRow::query()->create([...])` |
| **Dependency Injection** | Services im Constructor — Laravel fügt sie automatisch ein |
| **Enum** | `ReportStatus::Calculated`, `PaymentMethod::Dhs` |
| **Migration** | DB-Struktur in `database/migrations/` |
| **Seeder** | Start-Daten (Ärzte, Preise) in `database/seeders/` |

---

## Empfohlener 7-Tage-Plan

| Tag | Aufgabe | Doku |
|---|---|---|
| 1 | README + PROJECT_OVERVIEW lesen, `./bin/serve`, einloggen | `README.md` |
| 2 | `routes/api.php` + `DailyReportController` | `docs/API.md` |
| 3 | `DailyReportImportService` komplett durchgehen | `docs/WORKFLOWS.md` |
| 4 | Parser + Validation Tests lesen + laufen lassen | `tests/Unit/README.md` |
| 5 | Models + DATABASE_SCHEMA | `docs/DATABASE_SCHEMA.md` |
| 6 | Lab + Payment Services + Tests | `docs/SERVICES.md` |
| 7 | Export + Monthly Income + echten Import vergleichen | Original Excel vs Server |

---

## Ordner-Übersicht (ganz kurz)

```
app/
  Http/Controllers/   ← dünn: nur HTTP rein/raus
  Services/           ← HIER ist die Business-Logik (80 % deiner Zeit)
  Models/             ← Datenbank-Tabellen
  Support/            ← kleine Helfer (Geld, Hash, Kataloge)
  Enums/              ← feste Status-Werte (calculated, needs_review, …)

database/
  migrations/         ← Tabellen-Struktur
  seeders/            ← Startdaten (Ärzte, Preise)

tests/Unit/           ← lebende Spezifikation — unbedingt lesen!

docs/                 ← alles was du jetzt liest
```

---

## Nächster Schritt für dich

1. Öffne **`app/Services/Import/DailyReportImportService.php`**
2. Lies die Methode **`import()`** — das ist der Film vom Upload bis fertig
3. Springe von dort in **`processParsedReport()`** — Behandlung + Labor
4. Wenn du hängen bleibst: passenden **Unit-Test** in `tests/Unit/` suchen

> **Tipp:** Ändere nie zuerst den Export oder das Monatslohn — fang immer beim **Import einer Zeile** an. Wenn eine Zeile stimmt, stimmt irgendwann der ganze Monat.

---

## Weiterführend

| Dokument | Wann |
|---|---|
| [WORKFLOWS.md](./WORKFLOWS.md) | Technischer Ablauf mit Diagrammen |
| [TREATMENT_RULES.md](./TREATMENT_RULES.md) | Was Mitarbeiter ins Excel schreiben müssen |
| [DECISIONS.md](./DECISIONS.md) | Warum das System so gebaut wurde |
| [tests/Unit/README.md](../tests/Unit/README.md) | Welcher Test was prüft |
