<?php

/**
 * Appendix 2: COMPREHENSIVE MODERATION REPORT (3 sections) — the official form, as data.
 * The screens, the validation rules and the PDF all read from here, so the wording lives in one place.
 */
return [
    'title' => 'COMPREHENSIVE MODERATION REPORT (3 sections)',
    'appendix' => 'APPENDIX 2: COMPREHENSIVE MODERATION REPORT',

    'periods' => ['first' => 'First semester', 'second' => 'Second semester', 'full' => 'Full year'],

    'levels' => ['YR 1', 'YR 2', 'YR 3', 'YR 4', 'Postgraduate'],

    /** Table 1 — levels of complexity of assessments (weighting must total 100 %). */
    'question_types' => [
        'recall_simple' => [
            'label' => 'Simple recall',
            'text' => 'Simple recall of facts, simple processes and short definitions for example typical multiple choice questions which require students to, for example, name a process, component (the upper arm muscle is …) or amount (the longest river in Africa is …) from memory without having to perform any interpretation.',
        ],
        'recall_complex' => [
            'label' => 'Complex recall',
            'text' => 'Complex recall of extended definitions, complex processes and explanations which would also not require interpretation but the question would require a longer more detailed answer, for example giving a related account of steps to set up a business or perform a fault analysis.',
        ],
        'application_simple' => [
            'label' => 'Application – simple, familiar problems',
            'text' => 'Application of knowledge to solve simple, familiar problems which would involve few variables and the task would be very similar to what had been taught (for example a simple calculation or case study analysis). The student would still, however, be required to analyse the problem into its components and to synthesise knowledge to reach a solution, though at a simple level.',
        ],
        'application_complex' => [
            'label' => 'Application – complex, unfamiliar problems',
            'text' => 'Application of knowledge to solve more complex and unfamiliar problems. This is distinguished from more simple problem solving where there are more variables and/or the student has to apply their knowledge to a new context, rather than one that has previously been taught. Furthermore, the student would be required to perform a more difficult analysis of what the problem involves and what knowledge would need to be synthesised to address a solution (this could be a complex, multi-stage calculation or a more challenging case study).',
        ],
        'analysis_simple' => [
            'label' => 'Simple critical analysis',
            'text' => 'Simple critical analysis in which the student is asked to pass judgement, give reasoned opinion on and make suggestions towards, for example, an issue, procedure or approach with reference to the typical knowledge and procedures of the field. This could be done through asking students to comment on issues or approaches in a short case study. At lower levels (for example year 1) this would typically be similar to what has been taught involving relatively few variables.',
        ],
        'analysis_complex' => [
            'label' => 'Complex critical analysis',
            'text' => 'More complex critical analysis would involve students’ operating in a relatively unfamiliar or new context with an increased number of variables.',
        ],
    ],

    /** Rated Poor 0 / Adequate 1 / Good 2 by the internal moderator at Gate 1. */
    'ratings' => [
        'heqf' => 'Alignment with HEQF level descriptors',
        'outcomes' => 'Alignment with subject outcomes',
        'cross_field' => 'Integration of critical cross-field outcomes',
        'clarity' => 'Clarity of instructions and questions',
        'language' => 'Accessibility of language',
        'time' => 'Time allocation',
    ],
    'rating_labels' => [0 => 'Poor', 1 => 'Adequate', 2 => 'Good'],

    /** Section 1, questions 1–3 (Yes / No + comments) — internal moderator, Gate 1. */
    's1_questions' => [
        'q1' => 'Was the assessment task moderated before the students completed the assessment?',
        'q2' => 'Is the assessment task (test, oral, practical, project) and memo/assessment criteria set at an appropriate HEQSF level?',
        'q3' => 'Is the allocation of marks in proportion to the complexity of the questions?',
    ],

    /** Section 2, questions 1–5 — examiner. */
    's2_examiner_questions' => [
        'q1' => 'Which types of questions/project assessment criteria (see Table 1) did most students not meet?',
        'q2' => 'Comment on the pass-rate (above), and any other indicators (e.g. student evaluations) as to how students fared in this assessment.',
        'q3' => 'Have the pass-rate and other indicators changed significantly compared to previous assessments this semester?',
        'q4' => 'What was done differently in the approach to this subject this semester/year, and how effective was it?',
        'q5' => 'What should be done differently next time this subject is taught and assessed?',
    ],

    /** Section 2, questions 6–7 — internal moderator, Gate 2. */
    's2_moderator_questions' => [
        'q6' => 'Is the marking of the assessor up to standard, accurate, fair, consistent?',
        'q7' => 'Is the marking of the assessor recommended for acceptance?',
    ],

    /** "Please comment on the following" — Section 2 question 8 (internal moderator) and Section 3 (external moderator). */
    'comment_items' => [
        'coverage' => 'The extent to which the assessment tasks cover the outcomes/content of the subject, in proportion to its relative importance.',
        'difficulty' => 'Whether the assessment tasks are at the appropriate level of difficulty (standard) for the exit level of the subject.',
        'relevance' => 'Whether the assessment tasks have industry/professional relevance.',
        'reliability' => 'The reliability of the assessment process (i.e. consistent marking, accurate results, detailed memorandum, comprehensive assessment criteria).',
        'validity' => 'The validity of the assessment methods and instruments (comment on whether it measures the selected content/practical skills and learning outcomes it intends to).',
        'feedback' => 'The quality of the feedback given to the students (not required for final summative assessment).',
    ],

    /** Additional Section 3 items (external moderator only). */
    's3_extra_items' => [
        'best_practice' => 'How the qualification compares to best practice/innovative practice nationally and internationally',
        'standards' => 'The extent to which the qualification standards are in accordance with HEQSF, SAQA level descriptors and professional/industrial council standards and/or significant employer requirements',
        'recommendations' => 'Any recommendations for changes and/or improvements in the best interests of the qualification, CPUT staff and ensuring the capability profile of our students.',
    ],

    'adjustments' => 'Are any general adjustments of the marks recommended?',
    'consensus' => 'DECLARATION: Consensus has been reached between the Examiner and the moderator/s',
];
