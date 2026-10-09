<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\AttachmentKind;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Attachment;
use App\Models\Subject;
use App\Models\User;
use App\Services\Audit;
use App\Services\FileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\ClassListMarksheet;
use Tests\TestCase;

class LifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const PW = 'correct-horse-battery';

    private User $examiner;
    private User $internal;
    private User $external;
    private User $hod;
    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $mk = fn ($n, $r) => User::create(['name' => $n, 'email' => strtolower($r->value).'@t.test', 'role' => $r, 'password' => self::PW]);
        $this->examiner = $mk('Eve Examiner', Role::Examiner);
        $this->internal = $mk('Ian Internal', Role::InternalModerator);
        $this->external = $mk('Xena External', Role::ExternalModerator);
        $this->hod = $mk('Hana Head', Role::Hod);
        $this->subject = Subject::create(['code' => 'CSC101', 'name' => 'Intro', 'hod_id' => $this->hod->id]);
    }

    // ── payload helpers ──────────────────────────────────────────────────────
    /** A valid, noisy PNG so it passes the "not empty" check. */
    private function sig(string $password = self::PW): array
    {
        $im = imagecreatetruecolor(120, 40);
        for ($x = 0; $x < 120; $x++) {
            for ($y = 0; $y < 40; $y++) {
                imagesetpixel($im, $x, $y, imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
            }
        }
        ob_start();
        imagepng($im, null, 0);

        return ['image' => 'data:image/png;base64,'.base64_encode(ob_get_clean()), 'password' => $password];
    }

    /** Examiner's Section 1 of the official form (weights add up to 100). */
    private function s1(int $recall = 20): array
    {
        return [
            'period' => 'first', 'year' => 2026, 'heqf_level' => 6, 'subject_level' => 'YR 2',
            'qualification' => 'National Diploma: Emergency Medical Care', 'qualification_code' => 'D2EMCA', 'assessment_date' => '2026-03-12',
            'weights' => ['recall_simple' => $recall, 'recall_complex' => 10, 'application_simple' => 30, 'application_complex' => 20, 'analysis_simple' => 15, 'analysis_complex' => 100 - $recall - 75],
        ];
    }

    private function s1Moderator(string $answer = 'YES'): array
    {
        return [
            'ratings' => ['heqf' => 2, 'outcomes' => 2, 'cross_field' => 1, 'clarity' => 2, 'language' => 1, 'time' => 0],
            'questions' => collect(array_keys(config('moderation_form.s1_questions')))->mapWithKeys(fn ($k) => [$k => ['answer' => $answer, 'comment' => $answer === 'NO' ? '' : 'Looks fine.']])->all(),
        ];
    }

    private function s2Examiner(int $registered = 30): array
    {
        return [
            'registered' => $registered, 'type_of_assessment' => 'Written test',
            'answers' => collect(array_keys(config('moderation_form.s2_examiner_questions')))->mapWithKeys(fn ($k) => [$k => "Examiner answer {$k}."])->all(),
        ];
    }

    private function s2Moderator(): array
    {
        return [
            'answers' => ['q6' => 'Marking is consistent and fair.', 'q7' => 'Yes, recommended for acceptance.'],
            'items' => collect(array_keys(config('moderation_form.comment_items')))->mapWithKeys(fn ($k) => [$k => "Moderator comment on {$k}."])->all(),
            'adjustments' => ['recommended' => 'NO', 'specify' => ''],
        ];
    }

    private function s3External(): array
    {
        return [
            'items' => collect(array_keys(config('moderation_form.comment_items') + config('moderation_form.s3_extra_items')))->mapWithKeys(fn ($k) => [$k => "External comment on {$k}."])->all(),
            'adjustments' => ['recommended' => 'YES', 'specify' => 'Add 2% to all marks.'],
        ];
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    private function xlsx(): UploadedFile
    {
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet()->setTitle('Marks');
        $ws->fromArray([['Student', 'T1', 'T2']], null, 'A1');
        $marks = [82, 67, 45, 71, 38, 90, 55, 49, '', 'ABS', 63, 77, 58, 41, 69, 52, 88, 34, 60, 73];
        foreach ($marks as $i => $m) {
            $ws->setCellValue('A'.($i + 2), 's'.$i);
            $ws->setCellValue('B'.($i + 2), $m);
            $ws->setCellValue('C'.($i + 2), 50);
        }
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'marks.xlsx', null, null, true);
    }

    // ── step helpers ─────────────────────────────────────────────────────────
    private function as(User $u): static
    {
        return $this->actingAs($u);
    }

    private function newAssessment(bool $withExternal = true, ?Subject $subject = null, string $number = 'Test 1'): Assessment
    {
        $subject ??= $this->subject;
        $this->as($this->examiner)->post('/assessments', [
            'subject_id' => $subject->id, 'number' => $number,
            'internal_moderator_id' => $this->internal->id, 'external_moderator_id' => $withExternal ? $this->external->id : null,
        ])->assertRedirect();

        return Assessment::where('subject_id', $subject->id)->where('number', $number)->firstOrFail();
    }

    private function submitSection1(Assessment $a): void
    {
        $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'PAPER', 'file' => $this->pdf('paper.pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'MEMO', 'file' => $this->pdf('memo.pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => $this->s1(), 'signature' => $this->sig()])->assertOk();
    }

    private function approveGate1(Assessment $a): void
    {
        $this->as($this->internal)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'APPROVED', 'consensus' => true, 's1_moderator' => $this->s1Moderator(), 'comments' => 'Good.', 'signature' => $this->sig()])->assertOk();
    }

    private function submitSection2Manual(Assessment $a, int $registered = 30): void
    {
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-post", [
            'source' => ['type' => 'manual', 'text' => "72\n48.5\n55\n30"], 'total_marks' => 100, 's2' => $this->s2Examiner($registered), 'signature' => $this->sig(),
        ])->assertOk();
    }

    private function approveGate2(Assessment $a): void
    {
        $this->as($this->internal)->postJson("/assessments/{$a->id}/final-review", ['decision' => 'APPROVED', 'consensus' => true, 's2_moderator' => $this->s2Moderator(), 'signature' => $this->sig()])->assertOk();
    }

    // ── tests ────────────────────────────────────────────────────────────────
    public function test_full_lifecycle_with_external_moderator_and_section3_signoff(): void
    {
        $a = $this->newAssessment();
        $this->assertSame(AssessmentStatus::Draft, $a->status);

        // Phase 1 guards
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => $this->s1(), 'signature' => $this->sig()])
            ->assertStatus(400)->assertJsonPath('error', 'Upload the draft assessment paper first.');
        $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'PAPER', 'file' => UploadedFile::fake()->createWithContent('evil.pdf', 'MZ not a pdf')], ['Accept' => 'application/json'])->assertStatus(400);
        $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'PAPER', 'file' => $this->pdf('paper.pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'MEMO', 'file' => $this->pdf('memo.pdf')], ['Accept' => 'application/json'])->assertOk();
        $bad = $this->s1();
        $bad['weights']['recall_simple'] = 40; // total 120
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => $bad, 'signature' => $this->sig()])->assertStatus(400)->assertSee('add up to 100%');
        $incomplete = $this->s1();
        unset($incomplete['qualification']);
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => $incomplete, 'signature' => $this->sig()])->assertStatus(422);
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => $this->s1(), 'signature' => $this->sig('nope')])->assertStatus(403);
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => $this->s1(), 'signature' => $this->sig()])->assertOk();
        $this->assertSame(AssessmentStatus::PendingPreModeration, $a->fresh()->status);
        $this->assertSame('D2EMCA', $a->fresh()->s1_examiner['qualification_code']);
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => $this->s1(), 'signature' => $this->sig()])->assertStatus(409); // no double submit

        // Gate 1: ratings + questions required, "No" needs a comment, then a revision loop, then approval
        $this->as($this->internal)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'APPROVED', 'consensus' => false, 's1_moderator' => $this->s1Moderator(), 'signature' => $this->sig()])->assertStatus(400);
        $this->as($this->internal)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'APPROVED', 'consensus' => true, 'signature' => $this->sig()])->assertStatus(422);
        $this->as($this->internal)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'APPROVED', 'consensus' => true, 's1_moderator' => $this->s1Moderator('NO'), 'signature' => $this->sig()])->assertStatus(400)->assertSee('Explain your');
        $this->as($this->internal)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'REVISION_REQUESTED', 'comments' => 'Question 2 is above level 7.'])->assertOk();
        $this->assertSame(AssessmentStatus::RevisionRequested, $a->fresh()->status);
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => $this->s1(), 'signature' => $this->sig()])->assertOk();
        $this->as($this->internal)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'APPROVED', 'consensus' => true, 's1_moderator' => $this->s1Moderator(), 'signature' => $this->sig('wrong')])->assertStatus(403);
        $this->approveGate1($a);
        $a->refresh();
        $this->assertSame(AssessmentStatus::ReadyForPostModeration, $a->status);
        $this->assertSame(0, $a->s1_moderator['ratings']['time']);

        // Phase 3: workbook → statistics → Section 2 questions 1–5
        $att = $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'MARKS', 'file' => $this->xlsx()], ['Accept' => 'application/json'])->assertOk()->json('attachment.id');
        $sheets = $this->as($this->examiner)->getJson("/assessments/{$a->id}/marks?attachment={$att}")->assertOk()->json('sheets');
        $t1 = collect($sheets[0]['columns'])->firstWhere('header', 'T1');
        $this->assertNotNull($t1);
        $this->assertSame(19, $t1['nonBlank']);
        $source = ['type' => 'excel', 'attachment_id' => $att, 'sheet' => 'Marks', 'column' => $t1['index']];
        $prev = $this->as($this->examiner)->postJson("/assessments/{$a->id}/calculate", ['source' => $source, 'total_marks' => 100])->assertOk()->json('stats');
        $this->assertSame(18, $prev['candidate_count']);
        $this->assertSame(1, $prev['invalid_entries']);
        $this->assertEquals(72.22, $prev['pass_rate']);
        $this->assertEquals(90, $prev['highest_mark']);
        $this->assertArrayNotHasKey('scores', $prev); // raw scores are never sent to the browser
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-post", ['source' => $source, 'total_marks' => 100, 's2' => $this->s2Examiner(10), 'signature' => $this->sig()])
            ->assertStatus(400)->assertSee('cannot be lower'); // fewer registered than marked
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-post", ['source' => $source, 'total_marks' => 100, 'signature' => $this->sig()])->assertStatus(422); // questions missing
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-post", ['source' => $source, 'total_marks' => 100, 's2' => $this->s2Examiner(20), 'signature' => $this->sig()])->assertOk();
        $a->refresh();
        $this->assertSame(AssessmentStatus::PendingFinalModeration, $a->status);
        $this->assertSame(18, $a->candidate_count);
        $this->assertSame('Written test', $a->s2_examiner['type_of_assessment']);

        // Gate 2 (internal): questions 6–8 + adjustments; then Gate 3 (external): Section 3
        $this->as($this->internal)->postJson("/assessments/{$a->id}/final-review", ['decision' => 'APPROVED', 'consensus' => true, 'signature' => $this->sig()])->assertStatus(422);
        $incomplete = $this->s2Moderator();
        $incomplete['adjustments'] = ['recommended' => 'YES', 'specify' => ''];
        $this->as($this->internal)->postJson("/assessments/{$a->id}/final-review", ['decision' => 'APPROVED', 'consensus' => true, 's2_moderator' => $incomplete, 'signature' => $this->sig()])->assertStatus(422);
        $this->approveGate2($a);
        $this->assertSame(AssessmentStatus::PendingExternalModeration, $a->fresh()->status);
        $this->as($this->external)->postJson("/assessments/{$a->id}/final-review", ['decision' => 'APPROVED', 'consensus' => true, 's2_moderator' => $this->s2Moderator(), 'signature' => $this->sig()])->assertStatus(422); // needs Section 3, not Section 2
        $this->as($this->external)->postJson("/assessments/{$a->id}/final-review", ['decision' => 'APPROVED', 'consensus' => true, 's3_external' => $this->s3External(), 'signature' => $this->sig()])->assertOk();
        $a->refresh();
        $this->assertSame(AssessmentStatus::PendingSection3Signoff, $a->status);
        $this->assertSame('Add 2% to all marks.', $a->s3_external['adjustments']['specify']);

        // Section 3 sign-off: only the examiner and the HOD, once each; the last signature completes the record
        $this->as($this->internal)->postJson("/assessments/{$a->id}/section3-sign", ['signature' => $this->sig()])->assertStatus(403);
        $this->as($this->external)->postJson("/assessments/{$a->id}/section3-sign", ['signature' => $this->sig()])->assertStatus(403);
        $this->as($this->hod)->postJson("/assessments/{$a->id}/section3-sign", ['signature' => $this->sig('wrong')])->assertStatus(403);
        $this->as($this->hod)->postJson("/assessments/{$a->id}/section3-sign", ['signature' => $this->sig()])->assertOk()->assertJsonPath('status', 'PENDING_SECTION3_SIGNOFF');
        $this->as($this->hod)->postJson("/assessments/{$a->id}/section3-sign", ['signature' => $this->sig()])->assertStatus(409); // already signed
        $this->assertFalse($a->fresh()->needsActionFrom($this->hod));
        $this->assertTrue($a->fresh()->needsActionFrom($this->examiner));
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/section3-sign", ['signature' => $this->sig()])->assertOk()->assertJsonPath('status', 'COMPLETED');

        $a->refresh();
        $this->assertSame(AssessmentStatus::Completed, $a->status);
        $this->assertNotNull($a->completed_at);
        $report = Attachment::where('assessment_id', $a->id)->where('kind', AttachmentKind::FinalReport->value)->firstOrFail();
        $bytes = app(FileStore::class)->get($report->storage_key);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame(hash('sha256', $bytes), $report->sha256);
        $this->assertTrue(app(Audit::class)->verify($a->id), 'audit hash chain is intact');

        // tampering is detected
        \App\Models\AuditLog::where('assessment_id', $a->id)->where('action', 'SECTION2_SUBMITTED')->update(['action' => 'SOMETHING_ELSE']);
        $this->assertFalse(app(Audit::class)->verify($a->id));

        // the report can be downloaded by the HOD, the marks workbook only by the examiner
        $this->as($this->hod)->get("/files/{$report->id}")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->as($this->hod)->get("/files/{$att}")->assertForbidden();
        $this->as($this->examiner)->get("/files/{$att}")->assertOk();
        // completed is final
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/section3-sign", ['signature' => $this->sig()])->assertStatus(409);
    }

    public function test_completes_at_gate_2_when_no_external_moderator_is_assigned(): void
    {
        $a = $this->newAssessment(false);
        $this->submitSection1($a);
        $this->approveGate1($a);
        $this->submitSection2Manual($a);
        $this->approveGate2($a);
        $a->refresh();
        $this->assertSame(AssessmentStatus::Completed, $a->status);
        $this->assertNotNull($a->attachments->firstWhere('kind', AttachmentKind::FinalReport));
        // Section 3 does not apply: nobody can sign it
        $this->as($this->hod)->postJson("/assessments/{$a->id}/section3-sign", ['signature' => $this->sig()])->assertStatus(409);
    }

    public function test_the_pdf_follows_the_official_form(): void
    {
        $a = $this->newAssessment();
        $this->submitSection1($a);
        $this->approveGate1($a);
        $this->submitSection2Manual($a);
        $this->approveGate2($a);
        $this->as($this->external)->postJson("/assessments/{$a->id}/final-review", ['decision' => 'APPROVED', 'consensus' => true, 's3_external' => $this->s3External(), 'signature' => $this->sig()])->assertOk();
        $data = app(\App\Services\ReportService::class)->data($a->fresh());
        $this->assertSame('D2EMCA', $data['s1e']['qualification_code']);
        $this->assertSame(30, $data['stats']['registered']);
        $this->assertSame(26, $data['stats']['absent']); // 30 registered, 4 marks
        $this->assertSame($data['sig']['s1']['external']['hash'], $data['sig']['s3']['external']['hash']); // one external signature, printed in each section
        $this->assertNull($data['sig']['s3']['hod']); // not signed yet
        $html = view('pdf.report', $data)->render();
        foreach (['APPENDIX 2: COMPREHENSIVE MODERATION REPORT', 'SECTION 1 (Pre-assessment)', 'SECTION 2 (Post-assessment)', 'SECTION 3: To be completed by the External Moderator',
            'Was the assessment task moderated before the students completed the assessment?', 'Is the marking of the assessor up to standard', 'How the qualification compares to best practice',
            'DECLARATION: Consensus has been reached', 'Add 2% to all marks.'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringStartsWith('%PDF', app(\App\Services\ReportService::class)->render($a->fresh()));
    }

    public function test_final_review_can_be_returned_to_the_examiner(): void
    {
        $a = $this->newAssessment(false);
        $this->submitSection1($a);
        $this->approveGate1($a);
        $this->submitSection2Manual($a);
        $this->as($this->internal)->postJson("/assessments/{$a->id}/final-review", ['decision' => 'REVISION_REQUESTED', 'comments' => 'Please recheck the totals.'])->assertOk();
        $this->assertSame(AssessmentStatus::ReadyForPostModeration, $a->fresh()->status);
    }

    public function test_access_control(): void
    {
        $a = $this->newAssessment();
        $this->as($this->internal)->post('/assessments', ['subject_id' => $this->subject->id, 'number' => 'X', 'internal_moderator_id' => $this->internal->id])->assertForbidden();
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'REVISION_REQUESTED', 'comments' => 'nope nope'])->assertForbidden();
        $this->as($this->external)->get("/assessments/{$a->id}")->assertNotFound(); // not handed to them yet
        $this->as($this->internal)->get("/assessments/{$a->id}")->assertOk();
        $this->as($this->hod)->get("/assessments/{$a->id}")->assertOk();
        $other = User::create(['name' => 'Other', 'email' => 'o@t.test', 'role' => Role::Examiner, 'password' => self::PW]);
        $this->as($other)->get("/assessments/{$a->id}")->assertNotFound();
        $this->as($this->examiner)->get('/admin')->assertForbidden();
        $this->as($this->hod)->get('/admin')->assertOk();
        auth()->logout();
        $this->get('/')->assertRedirect('/login');
        $this->get("/assessments/{$a->id}")->assertRedirect('/login');
    }

    public function test_one_person_can_be_examiner_and_head_of_department(): void
    {
        // the examiner is also the HOD of the subject: they must sign Section 3 twice, once per capacity
        $this->examiner->update(['extra_roles' => 'HOD']);
        $this->assertSame('Examiner · Head of Department', $this->examiner->fresh()->roleLabels());
        $subject = Subject::create(['code' => 'DUAL1', 'name' => 'Dual', 'department' => 'X', 'hod_id' => $this->examiner->id]);
        $a = $this->newAssessment(true, $subject);
        $this->submitSection1($a);
        $this->approveGate1($a);
        $this->submitSection2Manual($a);
        $this->approveGate2($a);
        $this->as($this->external)->postJson("/assessments/{$a->id}/final-review", ['decision' => 'APPROVED', 'consensus' => true, 's3_external' => $this->s3External(), 'signature' => $this->sig()])->assertOk();
        $this->as($this->examiner)->get("/assessments/{$a->id}")->assertOk()->assertSee('you sign twice');
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/section3-sign", ['signature' => $this->sig(), 'as' => 'HOD_SECTION3'])->assertOk();
        $this->assertSame(AssessmentStatus::PendingSection3Signoff, $a->fresh()->status);
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/section3-sign", ['signature' => $this->sig()])->assertOk();
        $this->assertSame(AssessmentStatus::Completed, $a->fresh()->status);
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/section3-sign", ['signature' => $this->sig()])->assertStatus(409);
        $data = app(\App\Services\ReportService::class)->data($a->fresh());
        $this->assertNotNull($data['sig']['s3']['hod']);
        $this->assertNotNull($data['sig']['s3']['examiner']);
    }

    public function test_extra_roles_grant_access_and_appear_in_pickers(): void
    {
        $this->hod->update(['extra_roles' => 'EXAMINER,INTERNAL_MODERATOR']);
        $this->as($this->hod)->get('/assessments/create')->assertOk()->assertSee($this->internal->name);
        $this->assertTrue($this->hod->fresh()->hasRole(\App\Enums\Role::Examiner));
        $this->assertTrue(User::withRole(\App\Enums\Role::InternalModerator)->whereKey($this->hod->id)->exists());
        $this->as($this->hod)->post('/admin/users', ['name' => 'Dual Person', 'email' => 'dp@t.test', 'roles' => ['HOD', 'EXAMINER'], 'password' => 'long-enough-pw'])->assertRedirect();
        $u = User::where('email', 'dp@t.test')->firstOrFail();
        $this->assertSame('EXAMINER', $u->role->value); // primary = first in the fixed order
        $this->assertSame('Examiner · Head of Department', $u->roleLabels());
        $this->as($this->hod)->post('/admin/users', ['name' => 'None', 'email' => 'none@t.test', 'password' => 'long-enough-pw'])->assertSessionHasErrors('roles');
        $this->as($this->hod)->patch("/admin/users/{$u->id}/roles", ['roles' => ['EXAMINER', 'INTERNAL_MODERATOR']])->assertRedirect();
        $this->assertSame('Examiner · Internal Moderator', $u->fresh()->roleLabels());
        $this->as($this->hod)->patch("/admin/users/{$this->hod->id}/roles", ['roles' => ['EXAMINER']])->assertSessionHas('error');
        $this->assertTrue($this->hod->fresh()->hasRole(\App\Enums\Role::Hod));
    }

    public function test_reviewer_comments_on_documents_go_back_to_the_examiner(): void
    {
        $a = $this->newAssessment();
        $this->submitSection1($a);
        $paper = Attachment::where('assessment_id', $a->id)->where('kind', AttachmentKind::Paper->value)->firstOrFail();
        // the examiner cannot comment on their own paper, a stranger cannot even see it
        $this->as($this->examiner)->postJson("/attachments/{$paper->id}/comments", ['body' => 'self note'])->assertForbidden();
        $this->as($this->external)->getJson("/attachments/{$paper->id}/comments")->assertNotFound();
        // the internal moderator comments on a passage and on the document as a whole
        $this->as($this->internal)->postJson("/attachments/{$paper->id}/comments", ['body' => 'Question 2 is above level 6.', 'quote' => 'Explain the difference', 'start_offset' => 12])->assertCreated();
        $this->as($this->internal)->postJson("/attachments/{$paper->id}/comments", ['body' => 'Add a cover page.'])->assertCreated();
        $this->as($this->internal)->getJson("/attachments/{$paper->id}/comments")->assertOk()->assertJsonCount(2, 'comments')->assertJsonPath('can.comment', true);
        $this->as($this->internal)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'REVISION_REQUESTED', 'comments' => 'See the comments in the paper.', 'signature' => $this->sig()])->assertOk();
        $this->assertStringContainsString('2 comments', \App\Models\Notification::where('user_id', $this->examiner->id)->latest('id')->value('message'));
        // the examiner reads them and marks one as addressed; the moderator can no longer comment while it is with the examiner
        $r = $this->as($this->examiner)->getJson("/attachments/{$paper->id}/comments")->assertOk()->assertJsonPath('can.address', true)->assertJsonPath('can.comment', false);
        $id = $r->json('comments.0.id');
        $this->as($this->examiner)->patchJson("/comments/{$id}", ['addressed' => true])->assertOk()->assertJsonPath('addressed', true);
        $this->as($this->internal)->postJson("/attachments/{$paper->id}/comments", ['body' => 'late'])->assertForbidden();
        $this->as($this->internal)->patchJson("/comments/{$id}", ['addressed' => false])->assertForbidden();
        $this->as($this->examiner)->get("/assessments/{$a->id}")->assertOk()->assertSee('Comments on your documents');
    }

    public function test_login_and_pages_render_for_every_stage(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back');
        $this->post('/login', ['email' => 'examiner@t.test', 'password' => 'bad'])->assertRedirect()->assertSessionHas('error');
        $this->post('/login', ['email' => 'examiner@t.test', 'password' => self::PW])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Welcome back, Eve');
        $a = $this->newAssessment();
        $this->get("/assessments/{$a->id}")->assertOk()->assertSee('Assessment details')->assertSee('Simple recall');
        $this->get('/assessments/create')->assertOk();
        $this->getJson('/notifications')->assertOk();

        // every panel renders for the person whose turn it is
        $this->submitSection1($a);
        $this->as($this->internal)->get("/assessments/{$a->id}")->assertOk()->assertSee('Alignment with HEQF level descriptors')->assertSee('Was the assessment task moderated');
        $this->approveGate1($a);
        $this->as($this->examiner)->get("/assessments/{$a->id}")->assertOk()->assertSee('Student marks');
        $this->submitSection2Manual($a);
        $this->as($this->internal)->get("/assessments/{$a->id}")->assertOk()->assertSee('Is the marking of the assessor up to standard')->assertSee('Examiner answer q1.');
        $this->approveGate2($a);
        $this->as($this->external)->get("/assessments/{$a->id}")->assertOk()->assertSee('How the qualification compares to best practice');
        $this->as($this->external)->postJson("/assessments/{$a->id}/final-review", ['decision' => 'APPROVED', 'consensus' => true, 's3_external' => $this->s3External(), 'signature' => $this->sig()])->assertOk();
        $this->as($this->hod)->get("/assessments/{$a->id}")->assertOk()->assertSee('Sign Section 3 as');
        $this->as($this->examiner)->get("/assessments/{$a->id}")->assertOk()->assertSee('Sign Section 3 as');
        $this->as($this->examiner)->get('/')->assertOk()->assertSee('Your turn');
        $this->as($this->hod)->postJson("/assessments/{$a->id}/section3-sign", ['signature' => $this->sig()])->assertOk();
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/section3-sign", ['signature' => $this->sig()])->assertOk();
        $this->as($this->hod)->get("/assessments/{$a->id}")->assertOk()->assertSee('Moderation complete');
    }

    public function test_class_list_xls_is_read_checked_and_saved(): void
    {
        $phe = Subject::create(['code' => 'PHE261S', 'name' => 'Pre-hospital care 2', 'hod_id' => $this->hod->id]);
        $a = $this->newAssessment(false, $phe, 'Test 2');
        $this->submitSection1($a);
        $this->approveGate1($a);

        $t1 = array_merge([72, 70, 45, 56, 47, 31, 49, 39], range(40, 61)); // 30 students
        $t2 = $t1;
        $t2[3] = null; // one student has no T2 mark
        $file = new UploadedFile(ClassListMarksheet::build($t1, $t2), 'PHE261S_OT01.xls', null, null, true);
        $att = $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'MARKS', 'file' => $file], ['Accept' => 'application/json'])->assertOk()->json('attachment.id');

        $sheet = $this->as($this->examiner)->getJson("/assessments/{$a->id}/marks?attachment={$att}")->assertOk()->json('sheets.0');
        $this->assertSame('class-list', $sheet['format']);
        $this->assertSame('PHE261S', $sheet['meta']['code']);
        $this->assertSame(['T1', 'T2', 'T3', 'T4'], array_column($sheet['columns'], 'header'));
        $t2col = $sheet['columns'][1];
        $this->assertSame(29, $t2col['nonBlank']);

        $source = ['type' => 'excel', 'attachment_id' => $att, 'sheet' => $sheet['name'], 'column' => $t2col['index']];
        $prev = $this->as($this->examiner)->postJson("/assessments/{$a->id}/calculate", ['source' => $source, 'total_marks' => 100])->assertOk()->json();
        $this->assertSame(29, $prev['stats']['candidate_count']);
        $this->assertSame(30, $prev['info']['enrolled']);
        $this->assertEquals(25, $prev['info']['weight']);
        $this->assertCount(1, $prev['warnings']);
        $this->assertStringContainsString('1 of 30 listed students have no mark', $prev['warnings'][0]);

        // a test that has not been written yet cannot be used
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/calculate", ['source' => ['column' => $sheet['columns'][2]['index']] + $source, 'total_marks' => 100])
            ->assertStatus(400)->assertJsonPath('error', 'No valid marks were found in the selected data.');

        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-post", ['source' => $source, 'total_marks' => 100, 's2' => $this->s2Examiner(30), 'signature' => $this->sig()])->assertOk();
        $a->refresh();
        $this->assertSame(30, $a->enrolled_count);
        $this->assertEquals(25, $a->test_weight);
        $this->assertSame(29, $a->candidate_count);
        $this->assertStringContainsString('PHE261S_OT01.xls › MAS - Marksheet › T2', $a->marks_source);

        $this->as($this->internal)->get("/assessments/{$a->id}")->assertOk()->assertSee('29 of 30 listed students have a mark in this test')->assertSee('test weight 25% of the year mark');
        $this->as($this->examiner)->get("/assessments/{$a->id}")->assertOk()->assertSee('29 of 30 listed students');
    }

    public function test_warns_when_the_class_list_belongs_to_another_subject(): void
    {
        $a = $this->newAssessment(false); // subject CSC101
        $this->submitSection1($a);
        $this->approveGate1($a);
        $file = new UploadedFile(ClassListMarksheet::build(range(30, 59), [], 'PHE261S'), 'list.xls', null, null, true);
        $att = $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'MARKS', 'file' => $file], ['Accept' => 'application/json'])->assertOk()->json('attachment.id');
        $sheet = $this->as($this->examiner)->getJson("/assessments/{$a->id}/marks?attachment={$att}")->json('sheets.0');
        $prev = $this->as($this->examiner)->postJson("/assessments/{$a->id}/calculate", ['source' => ['type' => 'excel', 'attachment_id' => $att, 'sheet' => $sheet['name'], 'column' => $sheet['columns'][0]['index']], 'total_marks' => 100])->assertOk()->json();
        $this->assertStringContainsString('This class list is for PHE261S, but this assessment belongs to CSC101', $prev['warnings'][0]);
    }
}
