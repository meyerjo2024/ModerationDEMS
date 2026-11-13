<?php

namespace App\Models;

use App\Enums\AttachmentKind;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['kind' => AttachmentKind::class, 'removed_at' => 'datetime'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    /** The name of the document slot (default: Assessment paper / Memorandum for older uploads). */
    public function getDocLabelAttribute(): string
    {
        return $this->label ?: $this->kind->label();
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
