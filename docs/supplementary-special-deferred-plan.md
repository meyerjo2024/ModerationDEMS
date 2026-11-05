# Supplementary, special (sick / aegrotat) and deferred assessments — plan

*Status: plan. Stage 1 already exists in the platform; Stages 2–3 are not built.*

## 1. The need

Besides T1–T7, a subject sometimes runs:

| Kind | Typical reason | Who sits it |
|---|---|---|
| **Supplementary** | Failed or missed a mark threshold | A few students |
| **Special** (sick / aegrotat) | Missed a test with an accepted medical or other reason | Specific students |
| **Deferred** | Assessment postponed to a later date (illness, clash, approved deferral) | Specific students, possibly later in the year or next period |

Each has its own paper that must be **moderated like any other**, and its **marks replace or update the marks of specific students** for the test they missed or failed.

## 2. What the platform does today (Stage 1 — built)

- `SUPP` (Supplementary) and `SPEC` (Special) sit beside T1–T7 on *New assessment*.
- They follow the full workflow: Section 1, Gate 1, Section 2, Gate 2, optional external, Section 3 sign-off, PDF.
- An assessment is identified by subject + number + **academic year** + optional **note**, so several sittings (e.g. "Aegrotat group A / B") can exist in one year.
- Deadlines (14 days before / after the date), reminders, test calendar and default moderator all work for them.
- The marks-sheet column is found by heading (`SUPP`, `SUPPLEMENTARY`, `SPEC`, `SPECIAL`).
- **Marks are anonymous.** Only the chosen column of marks is read; student names and numbers are never read or stored.

What this does *not* do: it does not know **which student** a mark belongs to, and does not **replace** an earlier mark. That is a deliberate privacy choice and is where the decisions below come in.

## 3. Add "Deferred" (small, recommended first)

- New assessment type `DEFR` (label *Deferred*), alias headings `DEFR`, `DEFERRED`.
- Optional field **"replaces"** (T1–T7 of the same subject and year) so the report can say *"Special assessment replacing T2"*. Free text note remains for the group.
- Shown in the title, dashboard and PDF header; no change to the workflow.

## 4. Student-level replacement — three options

| Option | How | Privacy | Effort | Recommendation |
|---|---|---|---|---|
| **A. Stay anonymous** | The platform moderates the paper and the results of that sitting only. Replacing individual students' marks stays in the official marks system / MAS sheet. | Best — nothing about students is stored | None | **Default** |
| **B. Pseudonymised replacement** | When a marks sheet is read, the student number is turned into a keyed hash (HMAC with a server secret) and only the hash is stored next to the mark. The platform can then match a student across sheets and substitute the supplementary / special / deferred mark for the original in the year statistics — without ever storing the number or name. | Good — hashes cannot be reversed without the secret; still personal data under POPIA, so needs sign-off | Medium | **If the HOD wants combined statistics** |
| **C. Full student records** | Store student numbers and names, link to a student register. | Weakest — large POPIA footprint | High | Not recommended |

### Option B in detail

1. **Roster for the sitting.** When creating a supplementary / special / deferred assessment the examiner selects **which test it replaces** and (optionally) uploads the list of eligible students, or simply uses the marks sheet of the sitting. Only keyed hashes are kept.
2. **Reason and approval, without health data.** Per sitting record *reason code* (supplementary / medical / other approved) and an **approval reference number** — never medical certificates or diagnoses.
3. **Marks.** The examiner uploads the sitting's marks column as usual. The platform matches each hash to the earlier test's column and produces:
   - statistics for the sitting alone (moderated as today), and
   - **combined statistics** for the original test with replacements applied (original mark overwritten for matched students, with an audit entry that replacement happened, not the marks).
4. **Rules are configurable** (institution decisions — see §6): replacement vs. best-of-two, cap on supplementary marks (e.g. maximum 50%), whether a failed special/deferred sitting leads to a supplementary.
5. **Report.** The PDF for the original test notes *"N marks replaced by Special assessment (reference …)"*; the sitting has its own report.
6. **Access.** Hashes and matching are visible only to the examiner and HOD; moderators see counts and statistics, as today.

## 5. Workflow and governance

- A special / deferred sitting needs the **same moderation** as a normal one, usually on a shorter timetable: allow a per-type deadline setting (e.g. pre-moderation 5 days before, post-moderation 7 days after).
- **Approval step (optional):** HOD confirms the sitting is approved (reference number) before the examiner can start it.
- **Audit:** creation, approval reference, replacement run, who ran it.
- **HOD view:** list of all supplementary / special / deferred sittings per subject and year, with counts and status; filter on the dashboard.
- **Reminders:** the planned date goes into the examiner's test calendar (already supported for Supplementary and Special).

## 6. Decisions needed

1. Is **Option A** (anonymous, replacement done in the official marks system) enough, or do you need **combined statistics** (Option B)?
2. Who decides the **replacement rule** — replace, best-of-two, capped at what %?
3. Is **Deferred** a separate type from **Special**, or the same?
4. What **approval reference** exists today (faculty office / medical certificate number)?
5. Are shorter **deadlines** for these sittings acceptable (and how long)?
6. Does POPIA sign-off exist for storing **hashed student numbers** (Option B)?

## 7. Stages

| Stage | Content | Status |
|---|---|---|
| 1 | Supplementary and Special as assessment types, academic year + note, own marks column | **Done** |
| 2 | Deferred type, "replaces T…" link, approval reference, per-type deadlines, HOD list of sittings | Planned (small) |
| 3 | Option B: hashed student matching, combined statistics, replacement rules, report note | Planned, after the decisions above |
