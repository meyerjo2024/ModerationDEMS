<?php

namespace App\Enums;

enum ModerationStage: string
{
    case PreModeration = 'PRE_MODERATION';
    case FinalInternal = 'FINAL_INTERNAL';
    case FinalExternal = 'FINAL_EXTERNAL';

    public function label(): string
    {
        return match ($this) {
            self::PreModeration => 'Gate 1 · Pre-moderation',
            self::FinalInternal => 'Gate 2 · Final (internal)',
            self::FinalExternal => 'Gate 3 · External',
        };
    }
}
