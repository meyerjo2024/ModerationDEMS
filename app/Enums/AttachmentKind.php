<?php

namespace App\Enums;

enum AttachmentKind: string
{
    case Paper = 'PAPER';
    case Memo = 'MEMO';
    case Marks = 'MARKS';
    case SampleScript = 'SAMPLE_SCRIPT';
    case FinalReport = 'FINAL_REPORT';

    public function label(): string
    {
        return match ($this) {
            self::Paper => 'Assessment paper',
            self::Memo => 'Memorandum',
            self::Marks => 'Student marks',
            self::SampleScript => 'Sample script',
            self::FinalReport => 'Moderation report',
        };
    }

    /** @return list<string> allowed file types */
    public function allowedTypes(): array
    {
        return match ($this) {
            self::Paper, self::Memo => ['pdf', 'docx', 'doc'],
            self::Marks => ['xlsx', 'csv'],
            self::SampleScript => ['pdf', 'png', 'jpg'],
            self::FinalReport => ['pdf'],
        };
    }
}
