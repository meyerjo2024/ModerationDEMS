# Examiner manual

You own an assessment from the first draft to the final signed report. You act in **three places**: starting the assessment and Section 1 (Phase 1), fixing feedback, and recording results (Phase 3).

## A. Start a new assessment

1. Click **New assessment** (header or dashboard banner).

![New assessment](../screenshots/new-assessment.png)

2. Choose the **subject code** and type the **assessment number** (e.g. `Test 1`, `Exam`).
3. Pick the **internal moderator**. Optionally pick an **external moderator** — they only see the record after the internal moderator has signed off.
4. Click **Continue**.

> You cannot choose yourself as a moderator, and a subject cannot have two assessments with the same number.

## B. Phase 1 — Section 1 (before the assessment)

![Section 1](../screenshots/section1-form.png)

1. **Types of questions.** For each type (Multiple choice, Essay, …) enter the **weighting %**, the **HEQF level** (5–10) and whether it is **aligned** with the level descriptor; add an optional comment. Use **Add question type** for more rows. The bar must reach **100 %**.
2. **Documents.** Upload the **draft assessment paper** and the **memorandum** (PDF or Word, up to 15 MB each). You can replace a file at any time before submitting.
3. **Sign.** Draw your signature, enter your password.
4. Click **Sign & submit**. Use **Save draft** to keep your work without submitting.

The button stays disabled until the weightings total 100 %, both documents are attached and you have signed (the hint under the button says what is missing).

Status becomes **Pending Pre-Moderation Review** and the internal moderator is notified.

## C. If a revision is requested

![Revision requested](../screenshots/examiner-revision-notice.png)

The record returns to **Revision Requested** with the moderator's feedback at the top. Make the changes (edit rows, replace the paper or memo), sign again and resubmit. This can repeat as often as needed; the revision number shows in the header.

## D. Phase 3 — Section 2 (after marking)

When the moderator approves, the status is **Ready for Post-Moderation**. Once the assessment has been written and marked, open the record again.

![Section 2](../screenshots/section2-marks-and-results.png)

**Step 1 – Student marks**
- **Excel / CSV:** upload the marks workbook (`.xlsx` or `.csv`, headings in row 1). Choose the **sheet** (if there are several) and the **assessment column**, e.g. `T1`.
- **Enter manually:** switch the tab and type one mark per line.
- Enter the **total marks available** (default 100).

**Step 2 – Calculated results** appear automatically: candidates, pass rate (≥ 50 %), class average, highest and lowest mark, plus a distribution chart. Entries that cannot be used (such as `ABS`) are listed as excluded. Check these look right before continuing.

**Step 3 – Sample scripts (optional).** Attach marked scripts (PDF, PNG, JPG) — ideally a high, an average and a low one.

**Step 4 – Commentary and signature.** Describe how students performed (e.g. "essay question 2 was poorly answered"), sign and click **Sign & submit for final moderation**.

> The numbers you see are a preview. When you sign, the system **recalculates them from your file**, so what the moderator sees is always consistent with your upload.
> The workbook itself is only visible to you. Moderators see the statistics and your sample scripts, not student numbers.

## E. If the record is returned

A moderator can send the results back with feedback. The record returns to **Ready for Post-Moderation**; correct the marks or commentary and submit again. Moderators sign again afterwards.

## F. After completion

When all signatures are in, the record shows **Moderation complete** and you can download the signed PDF.

![Completed record](../screenshots/record-completed.png)

## Tips

- Check the **Audit trail** in the sidebar if you are unsure what happened last.
- Keep column headings (e.g. `T1`) unique in your workbook.
- Marks as percentages (`62%`) or with a decimal comma (`45,5`) are understood.
- If a button is greyed out, look for the grey hint under it.
