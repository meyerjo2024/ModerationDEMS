<?php

return [
    'institution' => env('INSTITUTION_NAME', 'Cape Peninsula University of Technology'),

    // Logo printed at the top of the official moderation report (set DEMS_LOGO= to disable).
    'logo' => env('DEMS_LOGO', 'resources/branding/cput-logo.jpg'),
    'paper' => env('DEMS_PAPER', 'a4'), // 'a4' or 'letter' (the Word original is Letter)

    // "db" keeps files in PostgreSQL (default, survives redeploys); "disk" uses a Laravel filesystem disk.
    'storage' => env('DEMS_STORAGE', 'db'),
    'disk' => env('DEMS_DISK', 'local'),
    'max_upload_mb' => (int) env('DEMS_MAX_UPLOAD_MB', 15),

    // Comma-separated e-mail domains allowed to self-register (empty = any). e.g. cput.ac.za
    'register_domains' => env('DEMS_REGISTER_DOMAINS', ''),

    'max_marks_rows' => 20000,

    // The assessment number is one of these; it is linked to the column of the same name in the marks sheet (class list).
    'assessment_numbers' => ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'SUPP', 'SPEC'],
    // Supplementary and special assessments are moderated exactly like tests; they just carry a different name.
    'assessment_labels' => ['SUPP' => 'Supplementary', 'SPEC' => 'Special'],
    // Column headings in the marks sheet that belong to each assessment (T3 → "T3"; the others may use any of these).
    'marks_aliases' => ['SUPP' => ['SUPP', 'SUPPLEMENTARY', 'SUP'], 'SPEC' => ['SPEC', 'SPECIAL', 'SPECIAL ASSESSMENT']],
    // Headings the class-list reader offers as mark columns.
    'marks_column_pattern' => '/^(T\d+|SUPP\w*|SUP\d*|SPEC\w*|SPECIAL.*)$/i',

    // Moderation windows around the assessment date.
    'pre_moderation_days' => 14,   // pre-moderation must be finished this many days BEFORE the assessment
    'post_moderation_days' => 14,  // post-moderation must be finished this many days AFTER it
    'deadline_warn_days' => 3,
    'assessment_reminder_days' => 3, // examiners are reminded of a planned assessment from this many days before it     // reminders start this many days before a due date

    'gates' => [
        ['key' => 'setup', 'title' => 'Pre-Assessment', 'sub' => 'Examiner · Section 1'],
        ['key' => 'gate1', 'title' => 'Gate 1', 'sub' => 'Internal pre-moderation'],
        ['key' => 'harvest', 'title' => 'Post-Assessment', 'sub' => 'Examiner · Section 2'],
        ['key' => 'gate2', 'title' => 'Gate 2', 'sub' => 'Internal final review'],
        ['key' => 'gate3', 'title' => 'Gate 3', 'sub' => 'External (optional)'],
        ['key' => 'done', 'title' => 'Archive', 'sub' => 'PDF · HOD'],
    ],

    'audit_labels' => [
        'ASSESSMENT_CREATED' => 'Assessment created',
        'SECTION1_SAVED' => 'Section 1 draft saved',
        'FILE_UPLOADED' => 'File uploaded',
        'USER_REGISTERED' => 'Staff registration received',
        'USER_APPROVED' => 'Registration approved',
        'USER_REJECTED' => 'Registration rejected',
        'SUBJECTS_IMPORTED' => 'Subject list imported',
        'ASSESSMENTS_PLANNED' => 'Assessment dates planned',
        'SUBJECTS_CHOSEN' => 'Subjects chosen',
        'USER_ROLES_CHANGED' => 'User roles changed',
        'DOCUMENT_COMMENTED' => 'Comment added to a document',
        'SECTION1_SUBMITTED' => 'Section 1 signed & submitted',
        'PRE_REVIEW_APPROVED' => 'Pre-moderation approved',
        'PRE_REVIEW_REVISION' => 'Revision requested',
        'SECTION2_SUBMITTED' => 'Section 2 signed & submitted',
        'SECTION3_SIGNED' => 'Section 3 signed',
        'FINAL_REVIEW_APPROVED' => 'Final moderation approved',
        'FINAL_REVIEW_RETURNED' => 'Returned to examiner',
        'EXTERNAL_REVIEW_APPROVED' => 'External moderation approved (Section 3)',
        'EXTERNAL_REVIEW_RETURNED' => 'Returned by external moderator',
        'REPORT_GENERATED' => 'Final PDF report generated',
        'REPORT_EMAILED' => 'Report e-mailed to HOD',
        'REPORT_EMAIL_FAILED' => 'Report e-mail not delivered',
    ],
];
