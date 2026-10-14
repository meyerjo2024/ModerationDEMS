# AI check of the question paper — plan for a later stage

*Status: plan only. Nothing in this document is built yet.*

## 1. Idea

In **Section 1** the examiner enters the **% weighting of each question type** (the six simple/complex recall, application and analysis types on the official CPUT form) and uploads the **paper and memorandum**. An optional **AI check** would read those files and tell the examiner, before submitting to the moderator, whether the paper matches the Section 1 criteria.

"Criteria one" is taken to mean: the weighting table **plus** the six rating criteria — alignment with HEQF level descriptors, alignment with subject outcomes, integration of critical cross-field outcomes, clarity of instructions and questions, accessibility of language, and time allocation — and Section 1 questions 1–3. *(To confirm.)*

## 2. What the AI would do (in order of value)

1. **Classify and weigh.** Split the paper into questions and sub-questions, read each question's marks, assign each to one of the six types with a short reason, and compute the actual % per type. Compare with the examiner's entered weights and flag differences above a threshold (e.g. 10 percentage points).
2. **Suggest ratings.** For each of the six criteria, suggest 0 / 1 / 2 with the reason and the question numbers or quotes it is based on.
3. **Memo check.** Does the memo answer every question? Do the marks add up to the paper total? Gaps or ambiguities.
4. **Improvement tips.** e.g. "Q3 is recall but sits in the application band", "no question tests analysis".

Every statement must cite a question number or a quote from the paper so the examiner can verify it.

## 3. How it looks in the platform

- **Button** under the paper/memo upload — "AI check (optional)" — shown once a paper is attached. Shows progress (20–90 s).
- **Weighting comparison table:** type · your % · AI % · marks · flag. A button **Use AI weights** fills the form fields; the examiner can still edit them.
- **Question table:** question no. · marks · assigned type · reason. The examiner can change the type; every correction is stored (best data for improving the prompts later).
- **Criteria cards:** six suggested ratings with evidence, plus memo findings, labelled **advisory**.
- **Re-run:** the analysis is tied to the exact file version (SHA-256), so a revised upload offers a re-run.
- **Moderators:** the internal moderator sees the examiner's AI check **read-only**, collapsed, labelled advisory. The moderator's own 0/1/2 ratings are never pre-filled or altered by the AI, so moderation stays independent.
- **Final PDF:** no AI text on the official form by default. Optional one-line note ("AI-assisted self-check run on <date>") if the university wants it.

## 4. Technical design (Laravel app)

- **Reading the files**
  - PDFs (including scans) can be sent to the model directly; it reads text and images.
  - `.docx` → convert to text with tables preserved.
  - Password-protected `.docx` is decrypted only in the browser, so the server cannot read it: v1 says "remove the password or upload a PDF for the AI check".
- **Running it:** a background job writes to a new table `ai_analyses` (assessment, attachment fingerprint, status, result JSON, model, prompt version, token counts, created by). The page polls the status. The free Render plan has no worker, so run inline with polling, or add a small queue worker to the Docker image.
- **Prompt:** contains the form's own wording (the six type descriptions and criteria already live in `config/moderation_form.php`), HEQF level descriptors for levels 5–10, and a configurable time-per-mark rule. The answer must be returned in a fixed JSON structure that the app validates.
- **Models** (configurable): a mid-size model such as `claude-sonnet-5-5` by default, `claude-opus-5-5` as a "deep check", `claude-haiku-5-5` for the quick memo check.
- **Safeguards:** API key only in a Render environment variable; master on/off switch (`AI_ENABLED`); per-user daily limit and monthly cost cap; audit-trail entry for every run; paper text treated strictly as data (ignore instructions hidden inside papers — prompt injection); never send marks sheets or any student data.

### Example result structure

```json
{
  "questions": [
    {"no": "1.2", "marks": 5, "type": "recall_simple", "confidence": 0.9, "reason": "…", "quote": "…"}
  ],
  "weights": {"recall_simple": 20, "recall_complex": 10, "application_simple": 30, "application_complex": 20, "analysis_simple": 15, "analysis_complex": 5},
  "ratings": {"heqf": {"score": 2, "reason": "…", "evidence": ["Q3", "Q7"]}, "outcomes": {}, "cross_field": {}, "clarity": {}, "language": {}, "time": {}},
  "memo": {"covers_all_questions": true, "marks_total_ok": true, "issues": []},
  "tips": ["…"]
}
```

## 5. Decisions needed before building

1. **Confidentiality / POPIA.** Exam papers are confidential; sending them to an outside AI service means they leave CPUT. Needs sign-off from CPUT IT / legal. Options: a business agreement with no data retention, a service hosted in South Africa or the EU, or a model run inside the university.
2. **Advisory only?** Recommended: never blocks submission, never decides anything.
3. **Should moderators see it?** Recommended: read-only.
4. **Subject outcomes.** "Alignment with subject outcomes" needs the subject's outcomes — add an *Outcomes* text field per subject (could be imported with the subject list).
5. **Languages.** Any papers in Afrikaans or isiXhosa?
6. **Budget.** Monthly cost cap per department (cost grows with paper length).

## 6. Suggested stages

| Stage | Content |
|---|---|
| **0 — before any code** | Confidentiality decision. Collect 10–20 real papers + memos with the moderators' actual ratings as a test set, to measure how well the AI agrees with human moderators. |
| **1 — first build** | Classification, weighting comparison, question table, "Use AI weights" button. |
| **2** | Criteria suggestions, memo check, read-only moderator view. |
| **3** | Tuning from examiners' corrections, subject outcomes per subject, HOD view of weighting spread across papers. |

## 7. Risks

- **Wrong or invented claims** → every point carries a quote or question number.
- **Over-reliance** → labelled advisory; the examiner confirms every number.
- **Judgement calls** (Bloom classification is partly subjective) → CPUT/SAQA descriptors in the prompt; corrections tracked.
- **Diagrams/images in papers** → PDFs handle these better than converted Word text.
- **Cost abuse** → limits and caps (above).
