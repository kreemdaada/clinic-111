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

**Rule:** For lab treatments (MC, ZIR, POST, IMPL, IMPL-CR, IMPL-ZIR, ABT) always use:

```
CODE x QUANTITY
```

---

## Other treatments (no JOB)

These are **not** lab treatments. They do **not** go into column G or H–P:

CF, AF, SxP, RCT, EXO, REPAIR, REMOV, …

They can stay in free text for notes, but they must **not** replace the lab format above when JOB is needed.

---

## Quick checklist before saving the daily report

- [ ] All lab codes are **UPPERCASE**
- [ ] Every lab item has **`CODE x QUANTITY`**
- [ ] Multiple items use **` + `** (e.g. `ZIR x 2 + POST x 1`)
- [ ] No `| tooth` notation for POST, MC, ZIR, IMPL, ABT
- [ ] Quantity is the **real count**, not a tooth number

---

## After import — verify

1. Open **Extraction log** for the imported month
2. Check rows with ⚠ **Issues**
3. Compare **JOB** and **Income columns** with the Original Income file

See also: `docs/BUSINESS_RULES.md` (accounting formulas), `docs/WORKFLOWS.md` (import steps).
