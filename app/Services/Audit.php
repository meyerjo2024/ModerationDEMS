<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Carbon;

/**
 * Append-only audit log. Entries tied to an assessment are hash-chained (each hash covers the previous one)
 * so after-the-fact edits are detectable.
 */
class Audit
{
    public function log(string $action, ?string $assessmentId = null, ?int $userId = null, array $details = [], ?string $ip = null): AuditLog
    {
        $createdAt = Carbon::now('UTC')->setMicroseconds(0);
        $prev = $assessmentId
            ? AuditLog::where('assessment_id', $assessmentId)->orderByDesc('id')->value('hash')
            : null;

        return AuditLog::create([
            'assessment_id' => $assessmentId,
            'user_id' => $userId,
            'action' => $action,
            'details' => $details ?: null,
            'ip_address' => $ip,
            'prev_hash' => $prev,
            'hash' => $this->hash($prev, $assessmentId, $userId, $action, $details ?: null, $createdAt),
            'created_at' => $createdAt,
        ]);
    }

    /** Re-computes the chain for an assessment; false if any link is broken. */
    public function verify(string $assessmentId): bool
    {
        $prev = null;
        foreach (AuditLog::where('assessment_id', $assessmentId)->orderBy('id')->get() as $r) {
            $expected = $this->hash($prev, $r->assessment_id, $r->user_id, $r->action, $r->details ?: null, $r->created_at);
            if ($r->prev_hash !== $prev || $r->hash !== $expected) {
                return false;
            }
            $prev = $r->hash;
        }

        return true;
    }

    private function hash(?string $prev, ?string $assessmentId, ?int $userId, string $action, ?array $details, Carbon $at): string
    {
        return Hashing::content([
            'prevHash' => $prev,
            'assessmentId' => $assessmentId,
            'userId' => $userId,
            'action' => $action,
            'details' => $details,
            'createdAt' => $at->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ]);
    }
}
