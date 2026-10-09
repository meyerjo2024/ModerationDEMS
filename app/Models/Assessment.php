<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use App\Enums\AttachmentKind;
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
            'question_types' => 'array',
            'scores' => 'array',
            'completed_at' => 'datetime',
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
    public function scopeVisibleTo(Builder $q, User $u): Builder
    {
        return $q->where(function (Builder $w) use ($u) {
            $w->where('examiner_id', $u->id)
                ->orWhere('internal_moderator_id', $u->id)
                ->orWhere(fn (Builder $x) => $x->where('external_moderator_id', $u->id)
                    ->whereIn('status', [AssessmentStatus::PendingExternalModeration->value, AssessmentStatus::Completed->value]))
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
            && in_array($this->status, [AssessmentStatus::PendingExternalModeration, AssessmentStatus::Completed], true);
    }

    public function needsActionFrom(User $u): bool
    {
        return match ($this->status->actor()) {
            'examiner' => $this->examiner_id === $u->id,
            'internal' => $this->internal_moderator_id === $u->id,
            'external' => $this->external_moderator_id === $u->id,
            default => false,
        };
    }

    // ── attachments (newest first) ──────────────────────────────────────────
    public function latestAttachment(AttachmentKind $kind): ?Attachment
    {
        return $this->attachments->firstWhere('kind', $kind);
    }

    /** @return Collection<int, Attachment> */
    public function attachmentsOf(AttachmentKind $kind)
    {
        return $this->attachments->where('kind', $kind)->values();
    }

    public function title(): string
    {
        return $this->subject->code.' · '.$this->number;
    }
}
