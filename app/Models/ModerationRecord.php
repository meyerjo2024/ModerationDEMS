<?php

namespace App\Models;

use App\Enums\ModerationStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['stage' => ModerationStage::class, 'checklist' => 'array', 'consensus_reached' => 'boolean'];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function approved(): bool
    {
        return $this->decision === 'APPROVED';
    }
}
