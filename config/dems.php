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

    // Advertise demo logins on the sign-in page (only when seeded with DEMS_DEMO_DATA=true).
    'demo' => (bool) env('DEMS_DEMO_DATA', false),

    'max_marks_rows' => 20000,

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
