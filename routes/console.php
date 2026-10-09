<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('dems:deadlines', function (\App\Services\DeadlineReminders $r) {
    $this->info('Reminders sent: '.$r->run());
})->purpose('Remind people about pre-/post-moderation deadlines');

\Illuminate\Support\Facades\Schedule::command('dems:deadlines')->dailyAt('06:00');
