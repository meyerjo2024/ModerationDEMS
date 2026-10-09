<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $guarded = [];

    public function hod(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hod_id');
    }

    /** Examiners / internal moderators who chose this subject. */
    public function responsible(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_user')->withPivot('role');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }
}
