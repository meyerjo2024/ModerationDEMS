<?php

namespace App\Enums;

enum SignatureSection: string
{
    case ExaminerSection1 = 'EXAMINER_SECTION_1';
    case PreModerator = 'PRE_MODERATOR';
    case ExaminerSection2 = 'EXAMINER_SECTION_2';
    case FinalInternalModerator = 'FINAL_INTERNAL_MODERATOR';
    case ExternalModerator = 'EXTERNAL_MODERATOR';

    public function label(): string
    {
        return match ($this) {
            self::ExaminerSection1 => 'Examiner · Section 1',
            self::PreModerator => 'Gate 1 · Pre-moderation',
            self::ExaminerSection2 => 'Examiner · Section 2',
            self::FinalInternalModerator => 'Gate 2 · Final (internal)',
            self::ExternalModerator => 'Gate 3 · External',
        };
    }
}
