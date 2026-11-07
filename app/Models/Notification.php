<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Notification extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        // every new in-app notification is also pushed to the person's iPhones, once the transaction that created it has committed
        static::created(fn (Notification $n) => DB::afterCommit(fn () => app(\App\Services\PushSender::class)->notify($n)));
    }

    protected function casts(): array
    {
        return ['read' => 'boolean'];
    }
}
