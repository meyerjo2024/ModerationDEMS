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
        return ['kind' => AttachmentKind::class];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
