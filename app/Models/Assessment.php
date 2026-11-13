<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use App\Enums\AttachmentKind;
use App\Enums\ModerationStage;
use App\Enums\SignatureSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Assessment extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => AssessmentStatus::class,
            's1_examiner' => 'array',
            's1_moderator' => 'array',
            's2_examiner' => 'array',
            's2_moderator' => 'array',
            's3_external' => 'array',
            'scores' => 'array',
            'completed_at' => 'datetime',
            'assessment_date' => 'date',
            'subminimum' => 'boolean',
        ];
    }

    // ── relations ───────────────────────────────────────────────────────────
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function examiner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'examiner_id');
    }

    public function internalModerator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internal_moderator_id');
    }

    public function externalModerator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'external_moderator_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->latest('created_at')->latest('id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(ModerationRecord::class)->orderBy('id');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class)->orderBy('id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class)->orderBy('id');
    }

    // ── access control ──────────────────────────────────────────────────────
    /** Pre-moderation must be finished by this date (assessment date minus the configured days). */
    public function preDue(): ?\Carbon\CarbonImmutable
    {
        return $this->assessment_date ? \Carbon\CarbonImmutable::parse($this->assessment_date)->subDays((int) config('dems.pre_moderation_days'))->startOfDay() : null;
    }

    /** Post-moderation must be finished by this date (assessment date plus the configured days). */
    public function postDue(): ?\Carbon\CarbonImmutable
    {
        return $this->assessment_date ? \Carbon\CarbonImmutable::parse($this->assessment_date)->addDays((int) config('dems.post_moderation_days'))->startOfDay() : null;
    }

    /**
     * The deadline that applies at the current stage, or null (no date / finished).
     *
     * @return array{phase: string, due: \Carbon\CarbonImmutable, days: int, state: string}|null  days < 0 means overdue
     */
    public function deadline(): ?array
    {
        $pre = in_array($this->status, [AssessmentStatus::Draft, AssessmentStatus::RevisionRequested, AssessmentStatus::PendingPreModeration], true);
        $post = in_array($this->status, [AssessmentStatus::ReadyForPostModeration, AssessmentStatus::PendingFinalModeration, AssessmentStatus::PendingExternalModeration, AssessmentStatus::PendingSection3Signoff], true);
        $due = $pre ? $this->preDue() : ($post ? $this->postDue() : null);
        if (! $due) {
            return null;
        }
        $days = (int) now('UTC')->startOfDay()->diffInDays($due, false);

        return ['phase' => $pre ? 'Pre-moderation' : 'Post-moderation', 'due' => $due, 'days' => $days,
            'state' => $days < 0 ? 'overdue' : ($days <= (int) config('dems.deadline_warn_days') ? 'soon' : 'ok')];
    }

    public function scopeVisibleTo(Builder $q, User $u): Builder
    {
        return $q->where(function (Builder $w) use ($u) {
            $w->where('examiner_id', $u->id)
                ->orWhere('internal_moderator_id', $u->id)
                ->orWhere(fn (Builder $x) => $x->where('external_moderator_id', $u->id)
                    ->whereIn('status', [AssessmentStatus::PendingExternalModeration->value, AssessmentStatus::PendingSection3Signoff->value, AssessmentStatus::Completed->value]))
                ->orWhereHas('subject', fn (Builder $s) => $s->where('hod_id', $u->id));
        });
    }

    public function isVisibleTo(User $u): bool
    {
        if ($this->examiner_id === $u->id || $this->internal_moderator_id === $u->id) {
            return true;
        }
        if ($this->subject->hod_id === $u->id) {
            return true;
        }

        // External moderators only see a record once it is handed to them.
        return $this->external_moderator_id === $u->id
            && in_array($this->status, [AssessmentStatus::PendingExternalModeration, AssessmentStatus::PendingSection3Signoff, AssessmentStatus::Completed], true);
    }

    public function needsActionFrom(User $u): bool
    {
        return match ($this->status->actor()) {
            'examiner' => $this->examiner_id === $u->id,
            'internal' => $this->internal_moderator_id === $u->id,
            'external' => $this->external_moderator_id === $u->id,
            'signoff' => ($this->examiner_id === $u->id && ! $this->hasSection3Signature(SignatureSection::ExaminerSection3))
                || ($this->subject->hod_id === $u->id && ! $this->hasSection3Signature(SignatureSection::HodSection3)),
            default => false,
        };
    }

    // ── attachments (newest first) ──────────────────────────────────────────
    public function latestAttachment(AttachmentKind $kind): ?Attachment
    {
        return $this->attachments->firstWhere('kind', $kind);
    }

    /**
     * The examiner's current documents (the exam paper already contains the memorandum). Each has a name; the newest file
     * under a name is the current one, and a removed document drops out. Older Memorandum uploads still show.
     *
     * @return Collection<int, Attachment>
     */
    public function documents()
    {
        return $this->attachments->whereIn('kind', [AttachmentKind::Paper, AttachmentKind::Memo])->sortBy([['created_at', 'asc'], ['id', 'asc']])->groupBy(fn (Attachment $a) => mb_strtolower($a->doc_label))
            ->map(fn ($g) => $g->last())->reject(fn (Attachment $a) => $a->removed_at !== null)->values();
    }

    /** Every upload under the same document name, oldest first (Version 1, 2, 3 …), including superseded and removed ones. @return Collection<int, Attachment> */
    public function versionsOf(Attachment $att)
    {
        if (! in_array($att->kind, [AttachmentKind::Paper, AttachmentKind::Memo], true)) {
            return collect([$att]);
        }

        return $this->attachments->whereIn('kind', [AttachmentKind::Paper, AttachmentKind::Memo])->filter(fn (Attachment $x) => mb_strtolower($x->doc_label) === mb_strtolower($att->doc_label))
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])->values();
    }

    public function versionNumber(Attachment $att): int
    {
        return ((int) $this->versionsOf($att)->search(fn (Attachment $x) => $x->id === $att->id)) + 1;
    }

    /** 'final' = the version that passed pre-moderation; 'current' = latest while still being prepared or reviewed; 'superseded' = an earlier version. */
    public function versionState(Attachment $att): string
    {
        $v = $this->versionsOf($att);
        if ($v->last()->id !== $att->id) {
            return 'superseded';
        }
        $passed = ! in_array($this->status, [AssessmentStatus::Draft, AssessmentStatus::RevisionRequested, AssessmentStatus::PendingPreModeration], true);

        return $passed ? 'final' : 'current';
    }

    /** @return Collection<int, Attachment> */
    public function attachmentsOf(AttachmentKind $kind)
    {
        return $this->attachments->where('kind', $kind)->values();
    }

    /** Has this Section 3 signature been given since the external moderator's latest approval? */
    public function hasSection3Signature(SignatureSection $section): bool
    {
        $approvedAt = $this->records->where('stage', ModerationStage::FinalExternal)->where('decision', 'APPROVED')->last()?->created_at;

        return $this->signatures->where('section', $section)
            ->contains(fn ($s) => ! $approvedAt || $s->signed_at->greaterThanOrEqualTo($approvedAt));
    }

    /**
     * Pass/fail figures for the report, with the subminimum when the test has one. Null until the marks are submitted.
     *
     * @return array{total: int, pass_mark: float, passed: int, failed: int, subminimum: float|null, met: int|null, not_met: int|null, passed_but_below: int|null, text: string}|null
     */
    public function resultsSummary(): ?array
    {
        $scores = $this->scores;
        if (! is_array($scores) || $scores === [] || ! ($this->total_marks > 0)) {
            return null;
        }
        $total = (float) $this->total_marks;
        $pass = \App\Services\StatisticsCalculator::PASS_MARK;
        $pct = array_map(fn ($s) => (float) $s / $total * 100, $scores);
        $n = count($pct);
        $passed = count(array_filter($pct, fn ($p) => $p >= $pass - 1e-9));
        $out = ['total' => $n, 'pass_mark' => $pass, 'passed' => $passed, 'failed' => $n - $passed, 'subminimum' => null, 'met' => null, 'not_met' => null, 'passed_but_below' => null];
        $text = "Pass mark {$pass}%: {$passed} of {$n} students passed and ".($n - $passed).' failed.';
        if ($this->subminimum && (float) $this->subminimum_percent > 0) {
            $sub = (float) $this->subminimum_percent;
            $met = count(array_filter($pct, fn ($p) => $p >= $sub - 1e-9));
            $below = count(array_filter($pct, fn ($p) => $p >= $pass - 1e-9 && $p < $sub - 1e-9));
            $out = array_merge($out, ['subminimum' => $sub, 'met' => $met, 'not_met' => $n - $met, 'passed_but_below' => $below]);
            $text .= " Subminimum {$sub}%: {$met} of {$n} students met the subminimum and ".($n - $met).' did not'
                .($below > 0 ? " ({$below} reached the pass mark but not the subminimum)" : '').'.';
        }
        $out['text'] = $text;

        return $out;
    }

    public function getNumberLabelAttribute(): string
    {
        return config('dems.assessment_labels.'.$this->number, $this->number);
    }

    /** Marks-sheet headings that belong to this assessment (T3 → [T3]; supplementary → SUPP, SUPPLEMENTARY …; sick → SICK; deferred → DEFERRED …). */
    public function marksHeaders(): array
    {
        return config('dems.marks_aliases.'.$this->number, [mb_strtoupper($this->number)]);
    }

    public function title(): string
    {
        return $this->subject->code.' · '.$this->number_label.($this->label !== '' ? ' ('.$this->label.')' : '');
    }
}
