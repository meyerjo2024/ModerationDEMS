"""Builds docs/presentation/Moderation-Platform-Update-John-Meyer.pptx from the screenshots in deck-images/ and docs/screenshots/.
Run:  python3 docs/presentation/build/build_deck.py   (needs python-pptx and Pillow)"""
import os, copy
from pptx import Presentation
from pptx.util import Inches, Pt, Emu
from pptx.dml.color import RGBColor
from pptx.enum.text import PP_ALIGN, MSO_ANCHOR
from pptx.enum.shapes import MSO_SHAPE
from pptx.oxml.ns import qn
from lxml import etree
from PIL import Image

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..', '..'))
IMG = os.path.join(ROOT, 'docs', 'presentation', 'deck-images')
OLD = os.path.join(ROOT, 'docs', 'screenshots')
LOGO = os.path.join(ROOT, 'docs', 'design', 'drafts', 'logo.png')
OUT = os.path.join(ROOT, 'docs', 'presentation', 'Moderation-Platform-Update-John-Meyer.pptx')

BLUE = RGBColor(0x00, 0x66, 0x99); MID = RGBColor(0x00, 0x99, 0xCC); SEA = RGBColor(0x66, 0xCC, 0xCC)
INK = RGBColor(0x1D, 0x1D, 0x1F); INK2 = RGBColor(0x55, 0x5B, 0x61); GREY = RGBColor(0x8E, 0x8E, 0x93)
FOG = RGBColor(0xF2, 0xF6, 0xF9); LINE = RGBColor(0xD0, 0xD7, 0xDD); WHITE = RGBColor(0xFF, 0xFF, 0xFF)
RED = RGBColor(0xC6, 0x28, 0x28); GREEN = RGBColor(0x1F, 0x8A, 0x4C)
FONT = 'Calibri'
FOOT = 'Digital Examination Moderation System  ·  John Meyer  ·  Cape Peninsula University of Technology'

prs = Presentation()
prs.slide_width = Inches(13.333); prs.slide_height = Inches(7.5)
BLANK = prs.slide_layouts[6]
count = [0]


def tb(slide, x, y, w, h, text='', size=16, bold=False, color=INK, align=PP_ALIGN.LEFT, anchor=MSO_ANCHOR.TOP, font=FONT):
    s = slide.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h)); tf = s.text_frame; tf.word_wrap = True
    tf.margin_left = tf.margin_right = Inches(0.04); tf.margin_top = tf.margin_bottom = Inches(0.02); tf.vertical_anchor = anchor
    p = tf.paragraphs[0]; p.alignment = align; r = p.add_run(); r.text = text
    r.font.size = Pt(size); r.font.bold = bold; r.font.color.rgb = color; r.font.name = font
    return s


def bullets(slide, x, y, w, h, items, size=17, color=INK, gap=7):
    s = slide.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h)); tf = s.text_frame; tf.word_wrap = True
    tf.margin_left = tf.margin_right = Inches(0.04)
    first = True
    for it in items:
        p = tf.paragraphs[0] if first else tf.add_paragraph(); first = False
        head, _, rest = it.partition('||')
        p.space_after = Pt(gap)
        pPr = p._p.get_or_add_pPr(); pPr.set('marL', str(int(Inches(0.28)))); pPr.set('indent', str(-int(Inches(0.28))))
        bu = etree.SubElement(pPr, qn('a:buClr')); c = etree.SubElement(bu, qn('a:srgbClr')); c.set('val', '0099CC')
        bc = etree.SubElement(pPr, qn('a:buChar')); bc.set('char', '•')
        if rest:
            r = p.add_run(); r.text = head; r.font.bold = True; r.font.size = Pt(size); r.font.color.rgb = color; r.font.name = FONT
            r2 = p.add_run(); r2.text = rest; r2.font.size = Pt(size); r2.font.color.rgb = INK2; r2.font.name = FONT
        else:
            r = p.add_run(); r.text = head; r.font.size = Pt(size); r.font.color.rgb = color; r.font.name = FONT
    return s


def rect(slide, x, y, w, h, fill=FOG, line=None, radius=True):
    s = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE if radius else MSO_SHAPE.RECTANGLE, Inches(x), Inches(y), Inches(w), Inches(h))
    if radius: s.adjustments[0] = 0.06
    s.fill.solid(); s.fill.fore_color.rgb = fill
    if line is None: s.line.fill.background()
    else: s.line.color.rgb = line; s.line.width = Pt(0.75)
    s.shadow.inherit = False
    return s


def picture(slide, path, x, y, w, h, crop=None, border=True):
    im = Image.open(path)
    if crop:  # (l, t, r, b) as fractions
        W, H = im.size; im = im.crop((int(crop[0]*W), int(crop[1]*H), int(crop[2]*W), int(crop[3]*H)))
        tmp = path + '.crop.png'; im.save(tmp); path = tmp
    iw, ih = im.size; scale = min(w / iw, h / ih); pw, ph = iw * scale, ih * scale
    px, py = x + (w - pw) / 2, y + (h - ph) / 2
    pic = slide.shapes.add_picture(path, Inches(px), Inches(py), Inches(pw), Inches(ph))
    if border: pic.line.color.rgb = LINE; pic.line.width = Pt(0.75)
    return pic


def base(label, title, notes=''):
    s = prs.slides.add_slide(BLANK); count[0] += 1
    tb(s, 0.6, 0.38, 9, 0.3, label.upper(), 11, True, MID)
    tb(s, 0.6, 0.66, 12.1, 0.8, title, 30, True, INK)
    ln = s.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(0.6), Inches(1.42), Inches(0.7), Inches(0.05)); ln.fill.solid(); ln.fill.fore_color.rgb = MID; ln.line.fill.background()
    tb(s, 0.6, 7.08, 10, 0.3, FOOT, 9, False, GREY)
    tb(s, 12.0, 7.08, 0.8, 0.3, str(count[0]), 9, False, GREY, PP_ALIGN.RIGHT)
    if notes: s.notes_slide.notes_text_frame.text = notes
    return s


def card(slide, x, y, w, h, head, body, accent=BLUE, size=14, headsize=16):
    rect(slide, x, y, w, h, FOG, LINE)
    bar = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(x), Inches(y + 0.18), Inches(0.07), Inches(0.42)); bar.fill.solid(); bar.fill.fore_color.rgb = accent; bar.line.fill.background()
    tb(slide, x + 0.22, y + 0.14, w - 0.35, 0.5, head, headsize, True, accent)
    tb(slide, x + 0.22, y + 0.66, w - 0.38, h - 0.75, body, size, False, INK2)


# ───────────────────────── 1. Title ─────────────────────────
s = prs.slides.add_slide(BLANK); count[0] += 1
bg = s.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, 0, prs.slide_width, prs.slide_height); bg.fill.solid(); bg.fill.fore_color.rgb = BLUE; bg.line.fill.background()
band = s.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, Inches(5.9), prs.slide_width, Inches(1.6)); band.fill.solid(); band.fill.fore_color.rgb = RGBColor(0x00, 0x55, 0x80); band.line.fill.background()
s.shapes.add_picture(LOGO, Inches(0.8), Inches(0.9), height=Inches(1.7))
tb(s, 0.8, 2.95, 11.5, 0.4, 'PLATFORM UPDATE', 14, True, SEA)
tb(s, 0.8, 3.35, 11.8, 1.5, 'Digital Examination\nModeration System', 46, True, WHITE)
tb(s, 0.8, 5.05, 11.5, 0.5, 'Moderation from set paper to signed report, in one place', 20, False, RGBColor(0xDC, 0xEE, 0xF5))
tb(s, 0.8, 6.15, 8, 0.5, 'Presented by John Meyer', 24, True, WHITE)
tb(s, 0.8, 6.7, 10, 0.4, 'Cape Peninsula University of Technology', 14, False, RGBColor(0xDC, 0xEE, 0xF5))
s.notes_slide.notes_text_frame.text = ('Welcome. I am John Meyer. Last time I showed the first version of the platform. Today is the update: what is new since then, who it helps, '
    'and what comes next. About 20 minutes plus questions.')

# ───────────────────────── 2. Why ─────────────────────────
s = base('The reason', 'Why moderation is moving online',
         'Paper forms and e-mailed attachments make it hard to know who has what, which version was approved, and whether deadlines are met. The platform keeps one record per assessment from start to signed report.')
card(s, 0.6, 1.8, 3.9, 3.7, 'Before', 'Word and PDF files e-mailed back and forth.\n\nNo single view of what is overdue.\n\nHard to prove which version was approved and by whom.\n\nPaper forms and re-typed details.', RED)
card(s, 4.72, 1.8, 3.9, 3.7, 'Now', 'One record per assessment, with every document, comment and signature on it.\n\nDeadlines and reminders built in.\n\nDigital signatures and a tamper-evident audit trail.\n\nThe official CPUT Appendix 2 report is produced automatically.', GREEN)
card(s, 8.84, 1.8, 3.9, 3.7, 'What this update adds', 'Overview dashboards for heads of department and programme.\n\nA report for the Dean, with an AI-written discussion.\n\nMultiple documents with versions, and comments on Word and PDF.\n\nInstall on any device.', BLUE)
rect(s, 0.6, 5.75, 12.14, 0.95, FOG, LINE)
tb(s, 0.85, 5.9, 11.7, 0.7, 'Goal: every assessment is moderated before it is written, signed off after it is marked, and visible to the people who need to see it.', 17, True, INK, anchor=MSO_ANCHOR.MIDDLE)

# ───────────────────────── 3. Process ─────────────────────────
s = base('Overview', 'The process at a glance',
         'Six steps. Steps 1 to 2 finish 14 days before the assessment is written; steps 3 to 6 finish 14 days after. Gate 3 only applies when an external moderator is assigned.')
steps = [('1', 'Section 1', 'Examiner', 'Details, question weightings and the paper'), ('2', 'Gate 1', 'Internal moderator', 'Reviews the paper in the browser; approves or sends back'),
         ('3', 'Section 2', 'Examiner', 'Marks, statistics and comments'), ('4', 'Gate 2', 'Internal moderator', 'Final review and signature'),
         ('5', 'Gate 3', 'External moderator', 'Only if assigned: Section 3'), ('6', 'Archive', 'Examiner + HOD', 'Sign Section 3; PDF report archived')]
for i, (n, t, who, d) in enumerate(steps):
    x = 0.6 + i * 2.06
    rect(s, x, 1.85, 1.9, 3.1, FOG if i % 2 else WHITE, LINE)
    c = s.shapes.add_shape(MSO_SHAPE.OVAL, Inches(x + 0.15), Inches(2.0), Inches(0.5), Inches(0.5)); c.fill.solid(); c.fill.fore_color.rgb = BLUE; c.line.fill.background()
    ct = c.text_frame; ct.paragraphs[0].alignment = PP_ALIGN.CENTER; r = ct.paragraphs[0].add_run(); r.text = n; r.font.size = Pt(16); r.font.bold = True; r.font.color.rgb = WHITE; r.font.name = FONT
    tb(s, x + 0.12, 2.65, 1.7, 0.4, t, 18, True, INK); tb(s, x + 0.12, 3.05, 1.7, 0.4, who, 12.5, True, MID); tb(s, x + 0.12, 3.5, 1.7, 1.4, d, 12.5, False, INK2)
rect(s, 0.6, 5.25, 5.9, 1.45, FOG, LINE); tb(s, 0.8, 5.35, 5.5, 0.4, 'Before the assessment', 15, True, BLUE)
tb(s, 0.8, 5.78, 5.5, 0.9, 'Pre-moderation (steps 1–2) is finished 14 days before the assessment date.', 14, False, INK2)
rect(s, 6.84, 5.25, 5.9, 1.45, FOG, LINE); tb(s, 7.04, 5.35, 5.5, 0.4, 'After the assessment', 15, True, BLUE)
tb(s, 7.04, 5.78, 5.5, 0.9, 'Post-moderation (steps 3–6) is finished 14 days after the assessment date.', 14, False, INK2)

# ───────────────────────── 4. Roles ─────────────────────────
s = base('Roles', 'Who uses it, and what they see',
         'People can hold more than one role. Visibility follows the assignment: you only see assessments you are part of, except the HOD who sees their subjects, and the Head of Programme who sees the dashboard for their qualification only.')
roles = [('Examiner', 'Sets the assessment, uploads the paper, submits marks and signs.', BLUE), ('Internal moderator', 'Reviews papers and results; comments in the document; approves or sends back.', BLUE),
         ('External moderator', 'Independent review (Section 3) once the internal moderator has signed off.', BLUE), ('Head of Department', 'Sees all subjects, signs off, and reports to the Dean. Can delete practice records.', MID),
         ('Head of Programme', 'Sees the progress dashboard for the qualification(s) they head. No papers or marks.', MID), ('System Administrator', 'Runs the platform: accounts, approvals, uploads. No access to assessments or audit trails.', GREY)]
for i, (h, b, col) in enumerate(roles):
    x = 0.6 + (i % 3) * 4.12; y = 1.8 + (i // 3) * 2.45
    card(s, x, y, 3.95, 2.25, h, b, col, 14.5)
tb(s, 0.6, 6.7, 12, 0.35, 'A person can hold several roles, for example Examiner and Internal Moderator.', 13, False, GREY)

# ───────────────────────── 5. Getting started ─────────────────────────
s = base('Getting started', 'Getting an account',
         'Staff register themselves and choose the roles they need. The HOD or System Administrator approves. Admins can also upload a whole staff list in one go with a spreadsheet. Qualification codes are matched to qualifications once in Admin.')
picture(s, os.path.join(IMG, 'admin.png'), 6.4, 1.8, 6.35, 4.95, crop=(0, 0.08, 1, 0.92))
bullets(s, 0.6, 1.85, 5.6, 5, [
    'Register||  — staff choose their roles (Examiner, Moderators, Head of Programme) and wait for approval.',
    'Approve||  — the HOD or System Administrator checks the request and confirms the roles.',
    'Upload staff||  — an Excel or CSV list of names, e-mails and roles creates accounts in one go.',
    'Subjects||  — import the subject list (qualification, code, name); set qualification codes once.',
    'Safe sign-in||  — passwords of 10+ characters; each person can change theirs, or delete their account.'], 16)

# ───────────────────────── 6. My subjects ─────────────────────────
s = base('Examiners and moderators', 'My subjects and the test calendar',
         'Each examiner ticks their subjects and enters the date of every test. The system reminds them three days before. New this time: Sick and Deferred assessments next to Supplementary and Special, and a subminimum option per test.')
picture(s, os.path.join(IMG, 'my-subjects-subminimum.png'), 0.6, 1.75, 6.3, 5.0, crop=(0, 0.05, 1, 0.9))
bullets(s, 7.2, 1.85, 5.6, 5, [
    'Pick your subjects||  and your usual internal moderator.',
    'Date every test||  — T1 to T7, Supplementary, Special, Sick and Deferred.',
    'Subminimum||  — Yes or No per test, with the % the student must reach.',
    'Reminders||  three days before each test, and when a moderation deadline is close or missed.',
    'Historic dates||  are accepted, for assessments already written.'], 16.5)

# ───────────────────────── 7. Starting an assessment ─────────────────────────
s = base('Examiner', 'Starting an assessment: type it once',
         'The qualification name, its code, the subject code and the assessment date are chosen once at the start. They then appear on every section and on the final report, so nothing is retyped.')
picture(s, os.path.join(IMG, 'new-assessment-qualification.png'), 0.6, 1.75, 5.6, 5.1, crop=(0, 0, 1, 0.86))
picture(s, os.path.join(IMG, 'record-header.png'), 6.5, 1.8, 6.25, 2.0)
bullets(s, 6.5, 4.05, 6.25, 2.8, [
    'Subject, qualification and date||  are pulled through to every section and the PDF report.',
    'Qualification codes||  (for example D2EMCA) are set once by the administrator.',
    'Deadlines||  are worked out from the date: 14 days before and after.'], 15.5)

# ───────────────────────── 8. Documents & versions ─────────────────────────
s = base('Documents', 'Several documents, clear versions, no separate memo',
         'The exam paper already contains the memorandum, so only one upload is needed. An examiner can add as many other documents as the assessment has. A revised upload becomes Version 2, 3 and so on, and the version that passed pre-moderation is marked Final.')
picture(s, os.path.join(IMG, 'versions.png'), 0.6, 1.8, 7.1, 4.7)
bullets(s, 8.0, 1.85, 4.8, 5, [
    'Upload the paper||  — it already contains the memorandum.',
    'Add other documents||  — annexures, formula sheets, extra papers.',
    'Version 2, 3 …||  every revision is kept and labelled.',
    'Final||  marks the version that passed pre-moderation.',
    'Everything is read inline||  in the browser, with comments.'], 16)

# ───────────────────────── 9. Inline comments ─────────────────────────
s = base('Internal and external moderators', 'Comment on the document itself, and reply',
         'Moderators select text in a Word document or a PDF and comment on it. The examiner sees each comment when the paper comes back, can reply under it, and marks it addressed. PDFs can be commented page by page too.')
picture(s, os.path.join(IMG, 'pdf-comment.png'), 0.6, 1.75, 7.3, 5.0, crop=(0.04, 0.45, 0.70, 0.88))
picture(s, os.path.join(IMG, 'reply.png'), 8.15, 1.75, 4.6, 2.75, crop=(0.41, 0.64, 0.68, 0.86))
bullets(s, 8.2, 4.6, 4.55, 2.4, [
    'Select text||  in Word or PDF and comment.',
    'Reply||  — examiner and moderator talk under each comment.',
    'Mark addressed||  when the change is made.'], 14.5, gap=5)

# ───────────────────────── 10. Section 2 ─────────────────────────
s = base('Examiner', 'Section 2: marks, results and the subminimum',
         'The examiner uploads the marks or pastes them. The system calculates the statistics server-side; raw marks are anonymous. If the test has a subminimum, the report also shows how many students met it and how many passed or failed.')
picture(s, os.path.join(IMG, 'section2.png'), 0.6, 1.75, 7.4, 5.0, crop=(0, 0.04, 1, 0.9))
bullets(s, 8.1, 1.85, 4.7, 5, [
    'Marks||  from the class list column that matches the test (T1…T7, Supplementary, Special, Sick, Deferred).',
    'Statistics||  — pass rate, average, highest, lowest, distribution.',
    'Subminimum||  — how many met it, how many passed or failed.',
    'No student data||  leaves the system; marks are anonymous numbers.'], 16)

# ───────────────────────── 11. Signatures & report ─────────────────────────
s = base('Appendix 2', 'Signed and sealed: the official CPUT report',
         'Every gate needs the person to enter their password to sign. Every action is written to an audit trail where each entry covers the one before it. When all signatures are in, the official CPUT Appendix 2 PDF is produced.')
picture(s, os.path.join(OLD, 'report-page-1.png'), 8.7, 1.7, 3.9, 5.2)
bullets(s, 0.6, 1.85, 7.8, 5, [
    'Digital signatures||  — drawn and confirmed with the signer’s password.',
    'Audit trail||  — hash-chained: any change to the history is detectable.',
    'Official form||  — Appendix 2: Comprehensive Moderation Report, produced as a PDF.',
    'Pass and fail figures||  and the subminimum sit under the “general adjustments of the marks” question.',
    'The record can be deleted||  by the HOD only if it was a demo or practice run, with a password and a typed confirmation.'], 16.5)

# ───────────────────────── 12. HOD overview ─────────────────────────
s = base('Head of Department', 'The overview: every qualification and subject',
         'The HOD sees, per qualification: how many subjects have moderators, and how many assessments are pending, in process, completed or overdue. Click a qualification to see its subjects, then a subject for its assessments. Filter by staff member, coverage and date.')
picture(s, os.path.join(IMG, 'overview.png'), 0.6, 1.75, 7.9, 5.0, crop=(0, 0.1, 1, 0.84))
bullets(s, 8.5, 1.85, 4.3, 5, [
    'By qualification||  — pending, in process, completed, overdue.',
    'Drill down||  from qualification to subject.',
    'Coverage||  — subjects without an internal moderator or examiner.',
    'Filter||  by staff member (examiner or moderator) and by date.',
    'Head of Programme||  sees only this view, for their own qualification(s).'], 15.5)

# ───────────────────────── 13. Dean report ─────────────────────────
s = base('Head of Department', 'The report for the Dean',
         'A separate report from the per-assessment moderation report. It is always up to date, covers all subjects, and has no student names or individual marks. It can be downloaded as PDF or as an editable Word document.')
picture(s, os.path.join(IMG, 'dean-pdf-1.png'), 8.65, 1.65, 4.05, 5.3)
bullets(s, 0.6, 1.85, 7.8, 5, [
    'At a glance||  — numbers only, one line per qualification code, plus Term 1 to 4.',
    'Compliance||  — moderated before the test, on time, revisions.',
    'Quality||  — the moderators’ ratings on six areas.',
    'Results||  — pass rates by qualification, term and year level; falls and low rates.',
    'Matters for attention||  — mark adjustments, overdue items, subjects without moderators.',
    'Appendix||  — every assessment, in four terms. PDF or Word, any time.'], 16)

# ───────────────────────── 14. AI narrative ─────────────────────────
s = base('Head of Department', 'The discussion for the Dean: written with AI',
         'The HOD presses Write with AI. The AI, using Groq, writes six short paragraphs from the figures only. The HOD reads and edits every paragraph before downloading. There is also a plain draft that works without AI. Privacy: only summary figures, subject codes and written comments with e-mail addresses removed are sent; no student data and no staff names.')
picture(s, os.path.join(IMG, 'dean-narrative.png'), 0.6, 1.75, 7.6, 5.0, crop=(0, 0.02, 1, 0.9))
bullets(s, 8.5, 1.85, 4.3, 5, [
    'Write with AI||  — six paragraphs from the figures, in plain formal English.',
    'Quick draft||  — the same, without AI.',
    'You edit||  every paragraph; the PDF and Word include exactly what is on screen.',
    'Privacy||  — no student data, no staff names, e-mail addresses removed.'], 15.5)

# ───────────────────────── 15. Safe by design ─────────────────────────
s = base('Trust', 'Safe by design',
         'Security and privacy points. Marks are anonymous numbers. People only see what they are part of. Passwords are required for signing and deleting. The database has row-level security turned on. The AI only receives summary figures.')
pts = [('Right people, right records', 'Examiners and moderators see only their assessments; the external moderator only after sign-off; the Head of Programme only a dashboard.'),
       ('Signed and traceable', 'Signatures need the signer’s password; the audit trail is hash-chained and cannot be edited quietly.'),
       ('Anonymous marks', 'Raw marks are numbers only; the class list stays with the examiner.'),
       ('Controlled changes', 'Deleting a record needs the HOD’s password and a typed confirmation, and is logged.'),
       ('Data stays put', 'Hosted with a managed database; row-level security is on; secrets are kept out of the code.'),
       ('AI with limits', 'Only summary figures and subject codes are sent; the HOD edits the text before it is used.')]
for i, (h, b) in enumerate(pts):
    x = 0.6 + (i % 3) * 4.12; y = 1.8 + (i // 3) * 2.45
    card(s, x, y, 3.95, 2.25, h, b, BLUE, 14)

# ───────────────────────── 16. Any device ─────────────────────────
s = base('Access', 'Works on any device, and installs like an app',
         'In Chrome, Edge or on Android there is an Install app button; on iPhone and iPad it shows the Add to Home Screen steps. A native iPhone app for the App Store is prepared but still needs CPUT’s Apple developer account and a Mac build.')
picture(s, os.path.join(IMG, 'install-login-full.png'), 0.6, 1.9, 4.4, 4.3, crop=(0.5, 0.05, 1.0, 0.95))
tb(s, 0.6, 6.3, 4.4, 0.35, 'Chrome, Edge, Android', 12, True, GREY, PP_ALIGN.CENTER)
picture(s, os.path.join(IMG, 'install-ios.png'), 5.3, 1.7, 2.45, 4.9)
tb(s, 5.3, 6.65, 2.45, 0.35, 'iPhone and iPad', 12, True, GREY, PP_ALIGN.CENTER)
bullets(s, 8.0, 1.85, 4.8, 5, [
    'Install app||  button on the sign-in page, the top bar and the account page.',
    'iPhone and iPad||  — Share, then Add to Home Screen.',
    'Full-screen||  with a home-screen icon and quick links.',
    'Phone friendly||  — every screen works on a phone.',
    'App Store app||  — prepared; waiting for CPUT’s Apple account.'], 15.5)

# ───────────────────────── 17. Status & next ─────────────────────────
s = base('Roadmap', 'Where we are, and what comes next',
         'Be clear about what is live and what is planned. The AI paper check and the replacement-test handling are written as plans only. The iPhone app is prepared but not yet built or submitted.')
card(s, 0.6, 1.8, 3.9, 4.6, 'Live today', 'Full moderation workflow\n\nMultiple documents and versions\n\nInline comments, Word and PDF\n\nOverview and Dean report\n\nAI-written discussion (needs the Groq key)\n\nInstallable in the browser\n\nE-mail reminders (need mail set-up)', GREEN, 14)
card(s, 4.72, 1.8, 3.9, 4.6, 'Next', 'Apple developer account and Mac build for the iPhone app\n\nPush notifications and Face ID in the app\n\nFinal privacy wording\n\nMail from a CPUT address for reminders', BLUE, 14)
card(s, 8.84, 1.8, 3.9, 4.6, 'Planned, not built', 'AI check of the paper against the question-weighting table\n\nA replacement test linked to the original for supplementary, sick and deferred cases', GREY, 14)

# ───────────────────────── 18. Checklist / decisions ─────────────────────────
s = base('Next steps', 'What I need from you',
         'Close with the asks: names for the HOD roles, qualification codes, mail address, and a pilot group.')
bullets(s, 0.6, 1.85, 7.6, 5, [
    'Confirm the HODs and Heads of Programme||  so their roles and qualifications can be set up.',
    'Check the qualification codes||  for each qualification.',
    'Choose a pilot group||  of examiners and moderators for one term.',
    'Provide the CPUT mailbox||  (or an approved mail service) so reminders go out by e-mail.',
    'Apple account details||  from the person who holds CPUT’s developer account.'], 17)
rect(s, 8.55, 1.85, 4.2, 4.2, FOG, LINE)
tb(s, 8.8, 2.0, 3.8, 0.5, 'Questions?', 28, True, BLUE)
tb(s, 8.8, 2.85, 3.8, 0.5, 'John Meyer', 22, True, INK)
tb(s, 8.8, 3.4, 3.8, 0.9, 'Moderation platform · Cape Peninsula University of Technology', 14, False, INK2)
tb(s, 8.8, 4.7, 3.8, 1.0, 'Thank you.', 18, False, INK2)

prs.save(OUT)
print('saved', OUT, count[0], 'slides')
