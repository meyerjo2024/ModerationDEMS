<?php

namespace App\Services;

use App\Enums\AssessmentStatus as S;
use App\Models\Assessment;
use App\Models\AssessmentPlan;
use App\Models\Notification;

/** In-app reminders when a pre- or post-moderation deadline is close or missed. At most one per person and record per day. */
class DeadlineReminders
{
    public function __construct(private Notifier $notifier) {}

    public function run(): int
    {
        $sent = 0;
        Assessment::with('subject')->whereNotNull('assessment_date')->where('status', '!=', S::Completed->value)->get()->each(function (Assessment $a) use (&$sent) {
            $d = $a->deadline();
            if (! $d || $d['state'] === 'ok') {
                return;
            }
            $when = $d['days'] < 0 ? abs($d['days']).' day(s) overdue' : ($d['days'] === 0 ? 'due today' : "due in {$d['days']} day(s)");
            $msg = "Reminder: {$d['phase']} of {$a->title()} is {$when} (by {$d['due']->format('j M Y')}).";
            foreach ($this->people($a, $d['state']) as $id) {
                $already = Notification::where('user_id', $id)->where('assessment_id', $a->id)->where('message', 'like', 'Reminder:%')->where('created_at', '>=', now('UTC')->startOfDay())->exists();
                if (! $already) {
                    $this->notifier->inApp([$id], $a, $msg);
                    $sent++;
                }
            }
        });

        return $sent + $this->plannedAssessments();
    }

    /** Examiners are reminded of every test they planned that is written within the next few days (daily until the day itself). */
    private function plannedAssessments(): int
    {
        $sent = 0;
        $today = now('UTC')->startOfDay();
        $until = $today->copy()->addDays((int) config('dems.assessment_reminder_days'));
        AssessmentPlan::with(['subject', 'user'])->whereDate('assessment_date', '>=', $today->toDateString())->whereDate('assessment_date', '<=', $until->toDateString())->get()->each(function (AssessmentPlan $p) use (&$sent, $today) {
            if (! $p->user?->active) {
                return;
            }
            $days = (int) $today->diffInDays($p->assessment_date->copy()->startOfDay(), false);
            $when = $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in {$days} days");
            $msg = "Reminder: {$p->subject->code} {$p->number} is written {$when} ({$p->assessment_date->format('j M Y')}).".($p->started() ? '' : ' It has not been started in the system yet.');
            $already = Notification::where('user_id', $p->user_id)->where('message', 'like', "Reminder: {$p->subject->code} {$p->number} is written%")->where('created_at', '>=', $today)->exists();
            if (! $already) {
                Notification::create(['user_id' => $p->user_id, 'message' => $msg]);
                $sent++;
            }
        });

        return $sent;
    }

    /** @return list<int> whoever the record is waiting on; the HOD as well once it is overdue */
    private function people(Assessment $a, string $state): array
    {
        $ids = match ($a->status) {
            S::Draft, S::RevisionRequested, S::ReadyForPostModeration => [$a->examiner_id],
            S::PendingPreModeration, S::PendingFinalModeration => [$a->internal_moderator_id],
            S::PendingExternalModeration => [$a->external_moderator_id],
            S::PendingSection3Signoff => [$a->examiner_id, $a->subject->hod_id],
            default => [],
        };
        if ($state === 'overdue') {
            $ids[] = $a->subject->hod_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
