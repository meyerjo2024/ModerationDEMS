<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $guarded = [];

    /** The qualification names on this subject (a subject can be offered in several). @return list<string> */
    public function qualificationList(): array
    {
        return array_values(array_filter(array_map('trim', explode(', ', (string) $this->qualification))));
    }

    /** Qualification name + code pairs offered with this subject. @return list<array{name: string, code: string}> */
    public function qualificationOptions(): array
    {
        $names = $this->qualificationList();
        $codes = array_map('trim', explode(', ', (string) $this->qualification_code));

        return array_map(fn ($n, $i) => ['name' => $n, 'code' => $codes[$i] ?? ''], $names, array_keys($names));
    }

    /** Known code of each qualification (lower-cased name => code). @return array<string, string> */
    public static function qualificationCodes(): array
    {
        $out = [];
        foreach (static::whereNotNull('qualification')->get() as $s) {
            foreach ($s->qualificationOptions() as $o) {
                if ($o['code'] !== '') {
                    $out[mb_strtolower($o['name'])] = $o['code'];
                }
            }
        }

        return $out;
    }

    /** Every qualification name in use, for pickers. @return list<string> */
    public static function allQualifications(): array
    {
        $all = [];
        foreach (static::whereNotNull('qualification')->pluck('qualification') as $q) {
            foreach (array_filter(array_map('trim', explode(', ', (string) $q))) as $name) {
                $all[mb_strtolower($name)] = $name;
            }
        }
        ksort($all);

        return array_values($all);
    }

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
