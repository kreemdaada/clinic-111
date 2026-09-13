# Daily Report — Treatment Rules

**For staff who write treatment text in the daily Excel report.**

The system reads the treatment column and calculates **JOB (lab cost)** and **Income columns H–P**.  
If the text is not written in the standard format, numbers can be wrong or missing.

---

## Standard format

```
CODE x QUANTITY
```

| Rule | Required |
|---|---|
| Treatment code | **UPPERCASE** |
| Quantity | Number after `x` (or `X`, `×`) |
| Multiple treatments | Join with ` + ` (space-plus-space) |
| Separator between patients | ` \| ` (space-pipe-space) |

### Examples (correct)

```
ZIR x 2
ZIR x 2 + POST x 1
MC x 3 + IMPL-ZIR x 1
IMPL x 2 + ABT x 2
IMPL-CR x 4
POST x 1
```

---

## Lab treatment codes (JOB)

These codes affect **column G (JOB)** and **columns H–P** in the Income Excel:

| Code | Treatment |
|---|---|
| **MC** | Metal Ceramic Crown |
| **ZIR** | Zircon Crown |
| **POST** | Post |
| **IMPL** | Implant (`IMP` is also accepted) |
| **IMPL-CR** | Implant Crown (`IMP-CR` is also accepted) |
| **IMPL-ZIR** | Zircon Implant Crown |
| **ABT** | Abutment |

Always write the **full code** and **explicit quantity**:

```
ZIR x 2          ✓
POST x 1         ✓
IMPL-ZIR x 1     ✓
```

---

## Do not use (causes wrong JOB / wrong column)

| Wrong | Problem |
|---|---|
| `post \|4` | `4` is read as tooth number, not quantity 4 |
| `ZIR 546\|5` | Tooth notation — quantity may be guessed wrong |
| `zir x 2` | Use **UPPERCASE**: `ZIR x 2` |
| `ZIR 2` without `x` | Quantity may be inferred incorrectly |
| `ZIR×2` (no spaces) | Prefer `ZIR x 2` with spaces |

**Rule:** For lab-cost treatments (MC, ZIR, POST, IMPL, IMPL-CR, IMPL-ZIR, ABT, **REMOV**) always use:

```
CODE x QUANTITY
```

Invalid lines (e.g. `zircon 2` instead of `ZIR x 2`) produce import warnings and set the report to **needs_review**.

---

## Clinical treatments (work_item, usually no JOB)

These create **work items** but typically **no lab job** (column G):

CF, AF, SxP, RCT, RE-RCT, REPAIR, EXO, …

**REMOV** is different: it has lab cost (**100 AED**) and appears in Income columns H–P **and** in JOB.

---

## Quick checklist before saving the daily report

- [ ] All lab codes are **UPPERCASE**
- [ ] Every lab item has **`CODE x QUANTITY`**
- [ ] Multiple items use **` + `** (e.g. `ZIR x 2 + POST x 1`)
- [ ] No `| tooth` notation for POST, MC, ZIR, IMPL, ABT
- [ ] Quantity is the **real count**, not a tooth number

---

## After import — verify

1. Check **`GET /api/daily-reports/{id}/validation-summary`** (or web import review) for parser warnings
2. Open **Extraction log** for the imported month
3. Compare **JOB** and **Income columns** with the Original Income file

See also: `docs/WORKFLOWS.md` (extractor → parser → validation pipeline).
