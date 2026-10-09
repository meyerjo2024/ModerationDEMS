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
        $internal = User::where('id', $in['internal_moderator_id'])->withRole(Role::InternalModerator)->where('active', true)->first()
            ?? throw new WorkflowException('Choose a valid internal moderator.');
        $external = $ext ? (User::where('id', $ext)->withRole(Role::ExternalModerator)->where('active', true)->first()
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
    /** Draft save (any fields may be empty). @param array<string, mixed> $s1 */
    public function saveSection1(User $user, Assessment $a, array $s1): void
    {
        $this->assertExaminer($user, $a);
        $this->expect($a, S::Draft, S::RevisionRequested);
        $a->update(['s1_examiner' => ModerationForm::s1Examiner($s1)]);
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

    public function submitPre(User $user, Assessment $a, array $s1, array $sig, array $meta): void
    {
        $this->assertExaminer($user, $a);
        $this->expect($a, S::Draft, S::RevisionRequested);

        $form = ModerationForm::s1Examiner($s1);
        $total = array_sum($form['weights']);
        if (abs($total - 100) > 0.01) {
            throw new WorkflowException('The weightings across the task must add up to 100% (currently '.round($total, 2).'%).');
        }
        $a->load('attachments');
        $paper = $a->latestAttachment(AttachmentKind::Paper) ?? throw new WorkflowException('Upload the draft assessment paper first.');
        $memo = $a->latestAttachment(AttachmentKind::Memo) ?? throw new WorkflowException('Upload the memorandum first.');
        $this->signer->confirm($user, $sig);

        $revision = $a->revision + 1;
        $hash = Hashing::content(['section' => 1, 'id' => $a->id, 'revision' => $revision, 'form' => $form, 'paper' => $paper->sha256, 'memo' => $memo->sha256]);
        DB::transaction(function () use ($a, $user, $form, $sig, $meta, $revision, $hash) {
            $a = $this->lock($a, S::Draft, S::RevisionRequested);
            $a->update(['status' => S::PendingPreModeration, 's1_examiner' => $form, 'revision' => $revision]);
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
        $form = ModerationForm::s1Moderator($in['s1_moderator']);
        foreach ($form['questions'] as $k => $q) {
            if ($q['answer'] === 'NO' && $q['comment'] === '') {
                throw new WorkflowException('Explain your “No” answer to: '.config("moderation_form.s1_questions.$k"));
            }
        }
        $this->signer->confirm($user, $in['signature'] ?? []);
        $a->load('attachments');
        $hash = Hashing::content(['gate' => 1, 'id' => $a->id, 'revision' => $a->revision,
            'paper' => $a->latestAttachment(AttachmentKind::Paper)?->sha256, 'memo' => $a->latestAttachment(AttachmentKind::Memo)?->sha256,
            'section1' => $a->s1_examiner, 'moderator' => $form, 'comments' => $in['comments'] ?? '']);
        DB::transaction(function () use ($a, $user, $in, $meta, $hash, $form) {
            $a = $this->lock($a, S::PendingPreModeration);
            $a->update(['status' => S::ReadyForPostModeration, 's1_moderator' => $form]);
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
        [$result, $desc, , $info] = $this->resolveMarks($a, $source, $total);
        unset($result['scores']);

        return ['stats' => $result, 'source' => $desc, 'info' => $info, 'warnings' => $this->warnings($a, $result, $info)];
    }

    /** Sanity checks against the class list (never blocking). @return list<string> */
    private function warnings(Assessment $a, array $result, array $info): array
    {
        $w = [];
        if (! empty($info['code']) && strcasecmp($info['code'], $a->subject->code) !== 0) {
            $w[] = "This class list is for {$info['code']}, but this assessment belongs to {$a->subject->code}. Make sure you uploaded the right file.";
        }
        if (! empty($info['enrolled'])) {
            if ($result['candidate_count'] > $info['enrolled']) {
                $w[] = "More marks ({$result['candidate_count']}) than students on the class list ({$info['enrolled']}).";
            } elseif ($result['candidate_count'] < $info['enrolled']) {
                $missing = $info['enrolled'] - $result['candidate_count'];
                $w[] = "{$missing} of {$info['enrolled']} listed students have no mark in this test (absent or not captured) and are not counted as candidates.";
            }
        }

        return $w;
    }

    /** @param array<string, mixed> $s2 examiner's Section 2 (registered, type of assessment, questions 1–5) */
    public function submitPost(User $user, Assessment $a, array $source, float $total, array $s2, array $sig, array $meta): void
    {
        $this->assertExaminer($user, $a);
        $this->expect($a, S::ReadyForPostModeration);
        // Statistics are always recomputed server-side from the source — never trusted from the client.
        [$r, $desc, $fingerprint, $info] = $this->resolveMarks($a, $source, $total);
        $form = ModerationForm::s2Examiner($s2);
        if ($form['registered'] < $r['candidate_count']) {
            throw new WorkflowException("The number of registered candidates ({$form['registered']}) cannot be lower than the number with marks ({$r['candidate_count']}).");
        }
        $this->signer->confirm($user, $sig);

        $stats = \Illuminate\Support\Arr::only($r, ['candidate_count', 'pass_count', 'pass_rate', 'highest_mark', 'lowest_mark', 'class_average']);
        $hash = Hashing::content(['section' => 2, 'id' => $a->id, 'stats' => $stats, 'totalMarks' => $r['total_marks'], 'source' => $fingerprint, 'form' => $form]);
        DB::transaction(function () use ($a, $user, $r, $stats, $desc, $form, $sig, $hash, $meta, $info) {
            $a = $this->lock($a, S::ReadyForPostModeration);
            $a->update($stats + [
                'status' => S::PendingFinalModeration, 'total_marks' => $r['total_marks'], 'scores' => $r['scores'],
                'invalid_entries' => $r['invalid_entries'], 'marks_source' => $desc, 's2_examiner' => $form,
                'enrolled_count' => $info['enrolled'] ?? null, 'test_weight' => $info['weight'] ?? null,
            ]);
            $this->signer->record($a, $user, SignatureSection::ExaminerSection2, $sig['image'], $hash, $meta);
            $this->audit->log('SECTION2_SUBMITTED', $a->id, $user->id, $stats + ['source' => $desc, 'contentHash' => $hash], $meta['ip']);
            $this->notifier->inApp([$a->internal_moderator_id], $a, "{$a->title()} results are ready for final moderation.");
        });
        $this->notifier->email([$a->internal_moderator_id], $a, "Final moderation requested: {$a->title()}", "{$user->name} has submitted results and comments for final moderation.");
    }

    // ── Phase 4/5: Gate 2, Gate 3, completion ────────────────────────────────
    /**
     * Gate 2 (internal moderator, Section 2 questions 6–8) and Gate 3 (external moderator, Section 3).
     *
     * @param  array<string, mixed>  $in  decision, comments, consensus, signature, s2_moderator | s3_external
     */
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
        $form = $external ? ModerationForm::s3External($in['s3_external']) : ModerationForm::s2Moderator($in['s2_moderator']);
        $this->signer->confirm($user, $in['signature'] ?? []);

        $hash = Hashing::content(['gate' => $external ? 3 : 2, 'id' => $a->id,
            'stats' => [$a->candidate_count, $a->pass_rate, $a->class_average, $a->highest_mark, $a->lowest_mark],
            'section2' => $a->s2_examiner, 'form' => $form, 'comments' => $in['comments'] ?? '']);
        $completes = ! $external && ! $a->external_moderator_id;
        $next = $external ? S::PendingSection3Signoff : ($completes ? S::Completed : S::PendingExternalModeration);

        $pdf = DB::transaction(function () use ($a, $user, $in, $meta, $stage, $section, $external, $from, $form, $hash, $completes, $next) {
            $a = $this->lock($a, $from);
            $a->update([$external ? 's3_external' : 's2_moderator' => $form, 'status' => $next] + ($completes ? ['completed_at' => now('UTC')] : []));
            ModerationRecord::create(['assessment_id' => $a->id, 'stage' => $stage, 'reviewer_id' => $user->id, 'decision' => 'APPROVED', 'consensus_reached' => true, 'comments' => ($in['comments'] ?? null) ?: null]);
            $this->signer->record($a, $user, $section, $in['signature']['image'], $hash, $meta);
            $this->audit->log($external ? 'EXTERNAL_REVIEW_APPROVED' : 'FINAL_REVIEW_APPROVED', $a->id, $user->id, ['contentHash' => $hash], $meta['ip']);

            if ($completes) {
                return $this->archive($a, $user, $meta);
            }
            $this->notifier->inApp(
                $external ? [$a->examiner_id, $a->subject->hod_id] : [$a->external_moderator_id],
                $a,
                $external ? "{$a->title()} needs your Section 3 signature." : "{$a->title()} is ready for your external moderation.",
            );

            return null;
        });

        if ($completes) {
            $this->emailReportToHod($a, $pdf, $user, $meta);
        } elseif ($external) {
            $this->notifier->email([$a->examiner_id, $a->subject->hod_id], $a, "Section 3 signature required: {$a->title()}", "{$user->name} has completed Section 3. The examiner and the Head of Department must now sign.");
        } else {
            $this->notifier->email([$a->external_moderator_id], $a, "External moderation requested: {$a->title()}", "{$user->name} has completed internal moderation. Your review is requested.");
        }

        return $next;
    }

    /**
     * Section 3 sign-off after the external moderator: the examiner and the Head of Department each sign;
     * the last signature completes the moderation and archives the report.
     */
    public function section3Sign(User $user, Assessment $a, array $sig, array $meta, ?string $as = null): S
    {
        $this->expect($a, S::PendingSection3Signoff);
        $a->load(['records', 'signatures']);
        // One person can hold both capacities (a Head of Department who is also the examiner): they sign once for each.
        $open = array_values(array_filter([
            $a->examiner_id === $user->id ? SignatureSection::ExaminerSection3 : null,
            $a->subject->hod_id === $user->id ? SignatureSection::HodSection3 : null,
        ], fn ($s) => $s && ! $a->hasSection3Signature($s)));
        if (! $open && ! ($a->examiner_id === $user->id || $a->subject->hod_id === $user->id)) {
            throw WorkflowException::forbidden('Only the examiner and the Head of Department sign Section 3.');
        }
        if (! $open) {
            throw WorkflowException::conflict('You have already signed Section 3.');
        }
        $section = ($as && ($pick = SignatureSection::tryFrom($as)) && in_array($pick, $open, true)) ? $pick : $open[0];
        $this->signer->confirm($user, $sig);

        $hash = Hashing::content(['section' => 3, 'id' => $a->id, 'form' => $a->s3_external, 'signer' => $section->value]);
        $pdf = DB::transaction(function () use ($a, $user, $sig, $meta, $section, $hash) {
            $a = $this->lock($a, S::PendingSection3Signoff);
            $this->signer->record($a, $user, $section, $sig['image'], $hash, $meta);
            $this->audit->log('SECTION3_SIGNED', $a->id, $user->id, ['as' => $section->value, 'contentHash' => $hash], $meta['ip']);
            $a->load(['records', 'signatures']);
            $done = $a->hasSection3Signature(SignatureSection::ExaminerSection3) && $a->hasSection3Signature(SignatureSection::HodSection3);
            if (! $done) {
                $other = $section === SignatureSection::HodSection3 ? $a->examiner_id : $a->subject->hod_id;
                if ($other !== $user->id) {
                    $this->notifier->inApp([$other], $a, "{$user->name} signed Section 3 of {$a->title()}. Your signature is still needed.");
                }

                return null;
            }
            $a->update(['status' => S::Completed, 'completed_at' => now('UTC')]);

            return $this->archive($a, $user, $meta);
        });

        if ($pdf) {
            $this->emailReportToHod($a, $pdf, $user, $meta);

            return S::Completed;
        }

        return S::PendingSection3Signoff;
    }

    /** Phase 5 — inside the caller's transaction, so a PDF failure rolls the final step back cleanly. @return array{bytes: string, filename: string} */
    private function archive(Assessment $a, User $actor, array $meta): array
    {
        $bytes = $this->reports->render($a->fresh());
        $filename = preg_replace('/[^\w.-]+/', '_', "Moderation-Report_{$a->subject->code}_{$a->number}").'.pdf';
        Attachment::create(['assessment_id' => $a->id, 'kind' => AttachmentKind::FinalReport, 'filename' => $filename, 'mime_type' => 'application/pdf',
            'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'storage_key' => $this->files->put($bytes), 'uploaded_by_id' => $actor->id]);
        $this->audit->log('REPORT_GENERATED', $a->id, $actor->id, ['filename' => $filename, 'sha256' => hash('sha256', $bytes)], $meta['ip']);
        $this->notifier->inApp(array_filter([$a->examiner_id, $a->internal_moderator_id, $a->external_moderator_id, $a->subject->hod_id]), $a, "{$a->title()} moderation is complete.");

        return ['bytes' => $bytes, 'filename' => $filename];
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
    /** @return array{0: array, 1: string, 2: string, 3: array} [result, description, fingerprint, class-list info] */
    private function resolveMarks(Assessment $a, array $source, float $total): array
    {
        if (($source['type'] ?? '') === 'manual') {
            $text = (string) ($source['text'] ?? '');

            return [$this->calc->calculate($this->calc->parseManual($text), $total), 'Entered manually', hash('sha256', $text), []];
        }
        $att = $a->attachments()->where('id', $source['attachment_id'] ?? '')->where('kind', AttachmentKind::Marks->value)->first()
            ?? throw new WorkflowException('That marks file was not found on this assessment.');
        $col = $this->workbook->column($this->files->get($att->storage_key), $att->filename, (string) $source['sheet'], (int) $source['column']);
        $info = ['enrolled' => $col['enrolled'], 'weight' => $col['weight'], 'code' => $col['meta']['code'] ?? null, 'format' => $col['format']];

        return [
            $this->calc->calculate($col['values'], $total),
            "{$att->filename} › {$source['sheet']} › {$col['header']}",
            "{$att->sha256}:{$source['sheet']}:{$source['column']}",
            $info,
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
