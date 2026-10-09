<?php

namespace App\Services;

use App\Enums\AssessmentStatus as S;
use App\Enums\AttachmentKind;
use App\Enums\ModerationStage;
use App\Enums\Role;
use App\Enums\SignatureSection;
use App\Exceptions\WorkflowException;
use App\Mail\ReportReady;
use App\Models\Assessment;
use App\Models\Attachment;
use App\Models\ModerationRecord;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The moderation state machine. Every transition: checks the actor's assignment, re-authenticates
 * signers, locks the row (so a record can't be advanced twice), writes signature + audit + notifications.
 *
 * $meta is ['ip' => ?string, 'ua' => ?string].
 */
class Workflow
{
    public function __construct(
        private Signer $signer,
        private Audit $audit,
        private FileStore $files,
        private StatisticsCalculator $calc,
        private WorkbookReader $workbook,
        private Notifier $notifier,
        private ReportService $reports,
        private UploadValidator $uploads,
    ) {}

    /** Validation rules shared by controllers for Section 1 rows. */
    public static function questionRules(string $prefix = 'question_types'): array
    {
        return [
            $prefix => ['required', 'array', 'min:1', 'max:15'],
            "$prefix.*.type" => ['required', 'string', 'max:80'],
            "$prefix.*.weighting" => ['required', 'numeric', 'between:0,100'],
            "$prefix.*.heqf_level" => ['required', 'integer', 'between:5,10'],
            "$prefix.*.aligned" => ['required', 'boolean'],
            "$prefix.*.comment" => ['nullable', 'string', 'max:500'],
        ];
    }

    // ── create ───────────────────────────────────────────────────────────────
    public function create(User $user, array $in, array $meta): Assessment
    {
        if (! $user->hasRole(Role::Examiner)) {
            throw WorkflowException::forbidden('Only examiners can start an assessment.');
        }
        $ext = $in['external_moderator_id'] ?? null;
        if ((int) $in['internal_moderator_id'] === $user->id) {
            throw new WorkflowException('You cannot moderate your own assessment.');
        }
        if ($ext && ((int) $ext === $user->id || (int) $ext === (int) $in['internal_moderator_id'])) {
            throw new WorkflowException('The external moderator must be a different person.');
        }
        $subject = Subject::find($in['subject_id']) ?? throw new WorkflowException('Unknown subject.');
        $internal = User::where('id', $in['internal_moderator_id'])->where('role', Role::InternalModerator->value)->where('active', true)->first()
            ?? throw new WorkflowException('Choose a valid internal moderator.');
        $external = $ext ? (User::where('id', $ext)->where('role', Role::ExternalModerator->value)->where('active', true)->first()
            ?? throw new WorkflowException('Choose a valid external moderator.')) : null;
        if (Assessment::where('subject_id', $subject->id)->where('number', $in['number'])->exists()) {
            throw WorkflowException::conflict("{$subject->code} already has an assessment called “{$in['number']}”.");
        }

        return DB::transaction(function () use ($user, $subject, $internal, $external, $in, $meta) {
            $a = Assessment::create([
                'subject_id' => $subject->id, 'number' => $in['number'], 'examiner_id' => $user->id,
                'internal_moderator_id' => $internal->id, 'external_moderator_id' => $external?->id,
                'status' => S::Draft,
            ]);
            $this->audit->log('ASSESSMENT_CREATED', $a->id, $user->id, ['subject' => $subject->code, 'number' => $a->number], $meta['ip']);

            return $a;
        });
    }

    // ── Phase 1 ──────────────────────────────────────────────────────────────
    public function saveSection1(User $user, Assessment $a, array $rows): void
    {
        $this->assertExaminer($user, $a);
        $this->expect($a, S::Draft, S::RevisionRequested);
        $a->update(['question_types' => $rows]);
    }

    public function upload(User $user, Assessment $a, AttachmentKind $kind, string $filename, string $bytes, array $meta): Attachment
    {
        $this->assertExaminer($user, $a);
        $slots = [
            'PAPER' => [S::Draft, S::RevisionRequested], 'MEMO' => [S::Draft, S::RevisionRequested],
            'MARKS' => [S::ReadyForPostModeration], 'SAMPLE_SCRIPT' => [S::ReadyForPostModeration],
        ];
        $allowed = $slots[$kind->value] ?? throw new WorkflowException('That kind of file cannot be uploaded.');
        if (! in_array($a->status, $allowed, true)) {
            throw WorkflowException::conflict("Files can't be added at this stage.");
        }
        $v = $this->uploads->validate($kind, $filename, $bytes);
        $key = $this->files->put($bytes);
        $sha = hash('sha256', $bytes);

        return DB::transaction(function () use ($a, $kind, $v, $bytes, $sha, $key, $user, $meta) {
            $att = Attachment::create([
                'assessment_id' => $a->id, 'kind' => $kind, 'filename' => $v['filename'], 'mime_type' => $v['mime'],
                'size' => strlen($bytes), 'sha256' => $sha, 'storage_key' => $key, 'uploaded_by_id' => $user->id,
            ]);
            $this->audit->log('FILE_UPLOADED', $a->id, $user->id, ['kind' => $kind->value, 'filename' => $v['filename'], 'sha256' => $sha], $meta['ip']);

            return $att;
        });
    }

    public function submitPre(User $user, Assessment $a, array $rows, array $sig, array $meta): void
    {
        $this->assertExaminer($user, $a);
        $this->expect($a, S::Draft, S::RevisionRequested);
        $total = array_sum(array_column($rows, 'weighting'));
        if (abs($total - 100) > 0.01) {
            throw new WorkflowException('Question weightings must add up to 100% (currently '.round($total, 2).'%).');
        }
        $a->load('attachments');
        $paper = $a->latestAttachment(AttachmentKind::Paper) ?? throw new WorkflowException('Upload the draft assessment paper first.');
        $memo = $a->latestAttachment(AttachmentKind::Memo) ?? throw new WorkflowException('Upload the memorandum first.');
        $this->signer->confirm($user, $sig);

        $revision = $a->revision + 1;
        $hash = Hashing::content(['section' => 1, 'id' => $a->id, 'revision' => $revision, 'questionTypes' => $rows, 'paper' => $paper->sha256, 'memo' => $memo->sha256]);
        DB::transaction(function () use ($a, $user, $rows, $sig, $meta, $revision, $hash) {
            $a = $this->lock($a, S::Draft, S::RevisionRequested);
            $a->update(['status' => S::PendingPreModeration, 'question_types' => $rows, 'revision' => $revision]);
            $this->signer->record($a, $user, SignatureSection::ExaminerSection1, $sig['image'], $hash, $meta);
            $this->audit->log('SECTION1_SUBMITTED', $a->id, $user->id, ['revision' => $revision, 'contentHash' => $hash], $meta['ip']);
            $this->notifier->inApp([$a->internal_moderator_id], $a, "{$a->title()} is ready for your pre-moderation review.");
        });
        $this->notifier->email([$a->internal_moderator_id], $a, "Pre-moderation requested: {$a->title()}", "{$user->name} has submitted {$a->title()} for your pre-moderation review.");
    }

    // ── Phase 2: Gate 1 ──────────────────────────────────────────────────────
    /** @param array{decision: string, comments?: ?string, consensus?: bool, signature?: array} $in */
    public function preReview(User $user, Assessment $a, array $in, array $meta): S
    {
        if ($a->internal_moderator_id !== $user->id) {
            throw WorkflowException::forbidden('Only the assigned internal moderator can review this.');
        }
        $this->expect($a, S::PendingPreModeration);

        if ($in['decision'] === 'REVISION_REQUESTED') {
            DB::transaction(function () use ($a, $user, $in, $meta) {
                $a = $this->lock($a, S::PendingPreModeration);
                $a->update(['status' => S::RevisionRequested]);
                ModerationRecord::create(['assessment_id' => $a->id, 'stage' => ModerationStage::PreModeration, 'reviewer_id' => $user->id, 'decision' => 'REVISION_REQUESTED', 'comments' => $in['comments']]);
                $this->audit->log('PRE_REVIEW_REVISION', $a->id, $user->id, ['comments' => $in['comments']], $meta['ip']);
                $this->notifier->inApp([$a->examiner_id], $a, "Revision requested on {$a->title()}.");
            });
            $this->notifier->email([$a->examiner_id], $a, "Revision requested: {$a->title()}", "{$user->name} asked for changes: {$in['comments']}");

            return S::RevisionRequested;
        }

        if (empty($in['consensus'])) {
            throw new WorkflowException('Tick “Consensus reached” to approve.');
        }
        $this->signer->confirm($user, $in['signature'] ?? []);
        $a->load('attachments');
        $hash = Hashing::content(['gate' => 1, 'id' => $a->id, 'revision' => $a->revision,
            'paper' => $a->latestAttachment(AttachmentKind::Paper)?->sha256, 'memo' => $a->latestAttachment(AttachmentKind::Memo)?->sha256,
            'questionTypes' => $a->question_types, 'comments' => $in['comments'] ?? '']);
        DB::transaction(function () use ($a, $user, $in, $meta, $hash) {
            $a = $this->lock($a, S::PendingPreModeration);
            $a->update(['status' => S::ReadyForPostModeration]);
            ModerationRecord::create(['assessment_id' => $a->id, 'stage' => ModerationStage::PreModeration, 'reviewer_id' => $user->id, 'decision' => 'APPROVED', 'consensus_reached' => true, 'comments' => ($in['comments'] ?? null) ?: null]);
            $this->signer->record($a, $user, SignatureSection::PreModerator, $in['signature']['image'], $hash, $meta);
            $this->audit->log('PRE_REVIEW_APPROVED', $a->id, $user->id, ['contentHash' => $hash], $meta['ip']);
            $this->notifier->inApp([$a->examiner_id], $a, "{$a->title()} passed pre-moderation. You can run the assessment.");
        });
        $this->notifier->email([$a->examiner_id], $a, "Approved: {$a->title()}", "{$user->name} approved the assessment. After marking, return to upload results (Section 2).");

        return S::ReadyForPostModeration;
    }

    // ── Phase 3: data harvest ────────────────────────────────────────────────
    /** @return list<array{name: string, columns: list<array>}> */
    public function marksColumns(User $user, Assessment $a, string $attachmentId): array
    {
        $this->assertExaminer($user, $a);
        $att = $a->attachments()->where('id', $attachmentId)->where('kind', AttachmentKind::Marks->value)->first()
            ?? throw WorkflowException::notFound('Marks file not found.');

        return $this->workbook->inspect($this->files->get($att->storage_key), $att->filename);
    }

    /** Stateless preview — nothing is saved until the examiner signs. Raw scores are not returned. */
    public function preview(User $user, Assessment $a, array $source, float $total): array
    {
        $this->assertExaminer($user, $a);
        [$result, $desc] = $this->resolveMarks($a, $source, $total);
        unset($result['scores']);

        return ['stats' => $result, 'source' => $desc];
    }

    public function submitPost(User $user, Assessment $a, array $source, float $total, string $commentary, array $sig, array $meta): void
    {
        $this->assertExaminer($user, $a);
        $this->expect($a, S::ReadyForPostModeration);
        // Statistics are always recomputed server-side from the source — never trusted from the client.
        [$r, $desc, $fingerprint] = $this->resolveMarks($a, $source, $total);
        $this->signer->confirm($user, $sig);

        $stats = Arr::only($r, ['candidate_count', 'pass_count', 'pass_rate', 'highest_mark', 'lowest_mark', 'class_average']);
        $hash = Hashing::content(['section' => 2, 'id' => $a->id, 'stats' => $stats, 'totalMarks' => $r['total_marks'], 'source' => $fingerprint, 'commentary' => $commentary]);
        DB::transaction(function () use ($a, $user, $r, $stats, $desc, $commentary, $sig, $hash, $meta) {
            $a = $this->lock($a, S::ReadyForPostModeration);
            $a->update($stats + [
                'status' => S::PendingFinalModeration, 'total_marks' => $r['total_marks'], 'scores' => $r['scores'],
                'invalid_entries' => $r['invalid_entries'], 'marks_source' => $desc, 'examiner_commentary' => $commentary,
            ]);
            $this->signer->record($a, $user, SignatureSection::ExaminerSection2, $sig['image'], $hash, $meta);
            $this->audit->log('SECTION2_SUBMITTED', $a->id, $user->id, $stats + ['source' => $desc, 'contentHash' => $hash], $meta['ip']);
            $this->notifier->inApp([$a->internal_moderator_id], $a, "{$a->title()} results are ready for final moderation.");
        });
        $this->notifier->email([$a->internal_moderator_id], $a, "Final moderation requested: {$a->title()}", "{$user->name} has submitted results and commentary for final moderation.");
    }

    // ── Phase 4/5: Gate 2, Gate 3, completion ────────────────────────────────
    /** @param array{decision: string, comments?: ?string, consensus?: bool, checklist?: array, scripts_sampled?: int, signature?: array} $in */
    public function finalReview(User $user, Assessment $a, array $in, array $meta): S
    {
        $this->expect($a, S::PendingFinalModeration, S::PendingExternalModeration);
        $external = $a->status === S::PendingExternalModeration;
        if ($external ? $a->external_moderator_id !== $user->id : $a->internal_moderator_id !== $user->id) {
            throw WorkflowException::forbidden($external ? 'Only the assigned external moderator can review this.' : 'Only the assigned internal moderator can review this.');
        }
        $stage = $external ? ModerationStage::FinalExternal : ModerationStage::FinalInternal;
        $section = $external ? SignatureSection::ExternalModerator : SignatureSection::FinalInternalModerator;
        $from = $a->status;

        if ($in['decision'] === 'REVISION_REQUESTED') {
            DB::transaction(function () use ($a, $user, $in, $meta, $stage, $external, $from) {
                $a = $this->lock($a, $from);
                $a->update(['status' => S::ReadyForPostModeration]);
                ModerationRecord::create(['assessment_id' => $a->id, 'stage' => $stage, 'reviewer_id' => $user->id, 'decision' => 'REVISION_REQUESTED', 'comments' => $in['comments']]);
                $this->audit->log($external ? 'EXTERNAL_REVIEW_RETURNED' : 'FINAL_REVIEW_RETURNED', $a->id, $user->id, ['comments' => $in['comments']], $meta['ip']);
                $this->notifier->inApp([$a->examiner_id, $a->internal_moderator_id], $a, "{$a->title()} was returned for Section 2 corrections.");
            });
            $this->notifier->email([$a->examiner_id], $a, "Returned for corrections: {$a->title()}", "{$user->name} returned the results: {$in['comments']}");

            return S::ReadyForPostModeration;
        }

        if (empty($in['consensus'])) {
            throw new WorkflowException('Tick “Consensus reached” to approve.');
        }
        $byId = collect($in['checklist'] ?? [])->keyBy('id');
        $checklist = [];
        foreach (config('dems.quality_checks') as $id => $question) {
            $c = $byId->get($id);
            if (! $c || ! in_array($c['answer'] ?? null, ['YES', 'NO', 'NA'], true)) {
                throw new WorkflowException('Answer every quality-check question.');
            }
            $comment = trim($c['comment'] ?? '');
            if ($c['answer'] === 'NO' && $comment === '') {
                throw new WorkflowException("Explain your “No” answer for: {$question}");
            }
            $checklist[] = ['id' => $id, 'question' => $question, 'answer' => $c['answer'], 'comment' => $comment];
        }
        $this->signer->confirm($user, $in['signature'] ?? []);

        $hash = Hashing::content(['gate' => $external ? 3 : 2, 'id' => $a->id,
            'stats' => [$a->candidate_count, $a->pass_rate, $a->class_average, $a->highest_mark, $a->lowest_mark],
            'commentary' => $a->examiner_commentary, 'checklist' => $checklist, 'scriptsSampled' => $in['scripts_sampled'], 'comments' => $in['comments'] ?? '']);
        $completes = $external || ! $a->external_moderator_id;
        $next = $completes ? S::Completed : S::PendingExternalModeration;

        $pdf = DB::transaction(function () use ($a, $user, $in, $meta, $stage, $section, $external, $from, $checklist, $hash, $completes, $next) {
            $a = $this->lock($a, $from);
            $a->update($completes ? ['status' => $next, 'completed_at' => now('UTC')] : ['status' => $next]);
            ModerationRecord::create(['assessment_id' => $a->id, 'stage' => $stage, 'reviewer_id' => $user->id, 'decision' => 'APPROVED', 'consensus_reached' => true,
                'comments' => ($in['comments'] ?? null) ?: null, 'checklist' => $checklist, 'scripts_sampled' => $in['scripts_sampled']]);
            $this->signer->record($a, $user, $section, $in['signature']['image'], $hash, $meta);
            $this->audit->log($external ? 'EXTERNAL_REVIEW_APPROVED' : 'FINAL_REVIEW_APPROVED', $a->id, $user->id, ['contentHash' => $hash], $meta['ip']);

            if (! $completes) {
                $this->notifier->inApp([$a->external_moderator_id], $a, "{$a->title()} is ready for your external moderation.");

                return null;
            }
            // Phase 5 — generated inside the transaction so a failure rolls the approval back cleanly.
            $bytes = $this->reports->render($a->fresh());
            $filename = preg_replace('/[^\w.-]+/', '_', "Moderation-Report_{$a->subject->code}_{$a->number}").'.pdf';
            Attachment::create(['assessment_id' => $a->id, 'kind' => AttachmentKind::FinalReport, 'filename' => $filename, 'mime_type' => 'application/pdf',
                'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'storage_key' => $this->files->put($bytes), 'uploaded_by_id' => $user->id]);
            $this->audit->log('REPORT_GENERATED', $a->id, $user->id, ['filename' => $filename, 'sha256' => hash('sha256', $bytes)], $meta['ip']);
            $this->notifier->inApp(array_filter([$a->examiner_id, $a->internal_moderator_id, $a->external_moderator_id, $a->subject->hod_id]), $a, "{$a->title()} moderation is complete.");

            return ['bytes' => $bytes, 'filename' => $filename];
        });

        if ($pdf === null) {
            $this->notifier->email([$a->external_moderator_id], $a, "External moderation requested: {$a->title()}", "{$user->name} has completed internal moderation. Your review is requested.");
        } else {
            $this->emailReportToHod($a, $pdf, $user, $meta);
        }

        return $next;
    }

    private function emailReportToHod(Assessment $a, array $pdf, User $user, array $meta): void
    {
        try {
            $hod = $a->subject->hod;
            if (in_array(config('mail.default'), ['log', 'array'], true)) {
                return; // demo / development: no mail server configured, the report is simply available to download
            }
            Mail::to($hod->email)->send(new ReportReady($a->title(), $hod->name, $pdf['bytes'], $pdf['filename']));
            $this->audit->log('REPORT_EMAILED', $a->id, $user->id, ['to' => $hod->email], $meta['ip']);
        } catch (Throwable $e) {
            report($e);
            $this->audit->log('REPORT_EMAIL_FAILED', $a->id, $user->id, ['reason' => $e->getMessage()], $meta['ip']);
        }
    }

    // ── helpers ──────────────────────────────────────────────────────────────
    /** @return array{0: array, 1: string, 2: string} [result, description, fingerprint] */
    private function resolveMarks(Assessment $a, array $source, float $total): array
    {
        if (($source['type'] ?? '') === 'manual') {
            $text = (string) ($source['text'] ?? '');

            return [$this->calc->calculate($this->calc->parseManual($text), $total), 'Entered manually', hash('sha256', $text)];
        }
        $att = $a->attachments()->where('id', $source['attachment_id'] ?? '')->where('kind', AttachmentKind::Marks->value)->first()
            ?? throw new WorkflowException('That marks file was not found on this assessment.');
        $col = $this->workbook->column($this->files->get($att->storage_key), $att->filename, (string) $source['sheet'], (int) $source['column']);

        return [
            $this->calc->calculate($col['values'], $total),
            "{$att->filename} › {$source['sheet']} › {$col['header']}",
            "{$att->sha256}:{$source['sheet']}:{$source['column']}",
        ];
    }

    private function assertExaminer(User $user, Assessment $a): void
    {
        if ($a->examiner_id !== $user->id) {
            throw WorkflowException::forbidden('Only the examiner for this assessment can do that.');
        }
    }

    private function expect(Assessment $a, S ...$ok): void
    {
        if (! in_array($a->status, $ok, true)) {
            throw WorkflowException::conflict('This record has moved on and can no longer be changed that way. Please refresh.');
        }
    }

    /** Row lock inside a transaction: fails if someone else already moved the record. */
    private function lock(Assessment $a, S ...$from): Assessment
    {
        $fresh = Assessment::lockForUpdate()->with('subject')->findOrFail($a->id);
        if (! in_array($fresh->status, $from, true)) {
            throw WorkflowException::conflict('This record was just updated by someone else. Please refresh.');
        }

        return $fresh;
    }
}
