<?php

namespace App\Enums;

enum Role: string
{
    case Examiner = 'EXAMINER';
    case InternalModerator = 'INTERNAL_MODERATOR';
    case ExternalModerator = 'EXTERNAL_MODERATOR';
    case Hod = 'HOD';
    /** Runs the platform (accounts, registrations, subjects). Takes no part in moderation and sees no assessments. */
    case SystemAdmin = 'SYSTEM_ADMIN';

    public function label(): string
    {
        return match ($this) {
            self::Examiner => 'Examiner',
            self::InternalModerator => 'Internal Moderator',
            self::ExternalModerator => 'External Moderator',
            self::Hod => 'Head of Department',
            self::SystemAdmin => 'System Administrator',
        };
    }
}
