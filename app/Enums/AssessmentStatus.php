<?php

namespace App\Enums;

enum AssessmentStatus: string
{
    case Draft = 'DRAFT';
    case PendingPreModeration = 'PENDING_PRE_MODERATION';
    case RevisionRequested = 'REVISION_REQUESTED';
    case ReadyForPostModeration = 'READY_FOR_POST_MODERATION';
    case PendingFinalModeration = 'PENDING_FINAL_MODERATION';
    case PendingExternalModeration = 'PENDING_EXTERNAL_MODERATION';
    case PendingSection3Signoff = 'PENDING_SECTION3_SIGNOFF';
    case Completed = 'COMPLETED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingPreModeration => 'Pending Pre-Moderation Review',
            self::RevisionRequested => 'Revision Requested',
            self::ReadyForPostModeration => 'Ready for Post-Moderation',
            self::PendingFinalModeration => 'Pending Final Moderation Review',
            self::PendingExternalModeration => 'Pending External Moderation',
            self::PendingSection3Signoff => 'Pending Section 3 Sign-off',
            self::Completed => 'Completed',
        };
    }

    /** Who must act next: examiner | internal | external | none. */
    public function actor(): string
    {
        return match ($this) {
            self::Draft, self::RevisionRequested, self::ReadyForPostModeration => 'examiner',
            self::PendingPreModeration, self::PendingFinalModeration => 'internal',
            self::PendingExternalModeration => 'external',
            self::PendingSection3Signoff => 'signoff',
            self::Completed => 'none',
        };
    }

    public function nextStep(): string
    {
        return match ($this) {
            self::Draft => 'complete Section 1 and submit for pre-moderation',
            self::RevisionRequested => 'revise Section 1 and resubmit',
            self::PendingPreModeration => 'review the draft paper and memorandum (Gate 1)',
            self::ReadyForPostModeration => 'capture results once marking is complete (Section 2)',
            self::PendingFinalModeration => 'complete the final moderation review (Gate 2)',
            self::PendingExternalModeration => 'complete the external moderation (Gate 3, Section 3)',
            self::PendingSection3Signoff => 'sign Section 3 (examiner and Head of Department)',
            self::Completed => '',
        };
    }

    /**
     * Index of the journey step awaiting action (see config/dems.php gates).
     */
    public function gateIndex(bool $hasExternal): int
    {
        return match ($this) {
            self::Draft, self::RevisionRequested => 0,
            self::PendingPreModeration => 1,
            self::ReadyForPostModeration => 2,
            self::PendingFinalModeration => 3,
            self::PendingExternalModeration => $hasExternal ? 4 : 3,
            self::PendingSection3Signoff => 5,
            self::Completed => 6,
        };
    }

    /** Literal class names so Tailwind can see them. @return array{chip:string,dot:string,wash:string,ring:string} */
    public function tone(): array
    {
        return match ($this) {
            self::Draft => ['chip' => 'bg-slate-500/10 text-slate-600 dark:text-zinc-300', 'dot' => 'bg-slate-400 text-slate-400', 'wash' => 'from-slate-400/15', 'ring' => 'ring-slate-400/30'],
            self::PendingPreModeration => ['chip' => 'bg-amber-500/10 text-amber-700 dark:text-amber-300', 'dot' => 'bg-amber-500 text-amber-500', 'wash' => 'from-amber-400/20', 'ring' => 'ring-amber-400/30'],
            self::RevisionRequested => ['chip' => 'bg-rose-500/10 text-rose-700 dark:text-rose-300', 'dot' => 'bg-rose-500 text-rose-500', 'wash' => 'from-rose-400/20', 'ring' => 'ring-rose-400/30'],
            self::ReadyForPostModeration => ['chip' => 'bg-sky-500/10 text-sky-700 dark:text-sky-300', 'dot' => 'bg-sky-500 text-sky-500', 'wash' => 'from-sky-400/20', 'ring' => 'ring-sky-400/30'],
            self::PendingFinalModeration => ['chip' => 'bg-violet-500/10 text-violet-700 dark:text-violet-300', 'dot' => 'bg-violet-500 text-violet-500', 'wash' => 'from-violet-400/20', 'ring' => 'ring-violet-400/30'],
            self::PendingExternalModeration => ['chip' => 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300', 'dot' => 'bg-indigo-500 text-indigo-500', 'wash' => 'from-indigo-400/20', 'ring' => 'ring-indigo-400/30'],
            self::PendingSection3Signoff => ['chip' => 'bg-fuchsia-500/10 text-fuchsia-700 dark:text-fuchsia-300', 'dot' => 'bg-fuchsia-500 text-fuchsia-500', 'wash' => 'from-fuchsia-400/20', 'ring' => 'ring-fuchsia-400/30'],
            self::Completed => ['chip' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300', 'dot' => 'bg-emerald-500 text-emerald-500', 'wash' => 'from-emerald-400/20', 'ring' => 'ring-emerald-400/30'],
        };
    }
}
