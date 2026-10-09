<?php

namespace App\Services;

use App\Mail\Notice;
use App\Models\Assessment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

class Notifier
{
    /** In-app notifications (call inside the workflow transaction). @param array<int> $userIds */
    public function inApp(array $userIds, Assessment $a, string $message): void
    {
        foreach (array_unique($userIds) as $id) {
            Notification::create(['user_id' => $id, 'assessment_id' => $a->id, 'message' => $message]);
        }
    }

    /** Best-effort e-mail; never throws (delivery problems must not roll back the workflow). @param array<int> $userIds */
    public function email(array $userIds, Assessment $a, string $subject, string $message): void
    {
        try {
            $emails = User::whereIn('id', array_unique($userIds))->where('active', true)->pluck('email')->all();
            if ($emails) {
                Mail::to($emails)->send(new Notice($subject, $message, url('/assessments/'.$a->id)));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
