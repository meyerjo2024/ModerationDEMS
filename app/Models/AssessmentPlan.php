<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentPlan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['assessment_date' => 'date'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getNumberLabelAttribute(): string
    {
        return config('dems.assessment_labels.'.$this->number, $this->number);
    }

    /** Has the examiner already started this test in the system? */
    public function started(): bool
    {
        return Assessment::where('subject_id', $this->subject_id)->where('examiner_id', $this->user_id)->where('number', $this->number)->exists();
    }
}
