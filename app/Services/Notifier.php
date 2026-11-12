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
        $this->emailUsers($userIds, $subject, $message, url('/assessments/'.$a->id));
    }

    /** Best-effort e-mail to active people who have not switched e-mails off (one message each, so addresses stay private). @param array<int> $userIds */
    public function emailUsers(array $userIds, string $subject, string $message, ?string $url = null): void
    {
        try {
            $users = User::whereIn('id', array_unique(array_filter($userIds)))->where('active', true)->where('email_notifications', true)->get(['id', 'email']);
        } catch (Throwable $e) {
            report($e);

            return;
        }
        foreach ($users as $u) {
            try {
                Mail::to($u->email)->send(new Notice($subject, $message, $url ?? url('/')));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** Sends one message and returns the real error text, so Admin can see why delivery fails. Null = accepted by the mail server. */
    public function test(string $to, string $by): ?string
    {
        try {
            Mail::to($to)->send(new Notice('Moderation platform: test e-mail', "This is a test message sent by {$by}. If you can read it, e-mail notifications are working.", url('/'), 'Open the platform'));

            return null;
        } catch (Throwable $e) {
            report($e);

            return $e->getMessage();
        }
    }
}
