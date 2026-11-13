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
        $this->examiner = $mk('Test Examiner', Role::Examiner);
        $this->internal = $mk('Test Internal Moderator', Role::InternalModerator);
        $this->external = $mk('Test External Moderator', Role::ExternalModerator);
        $this->hod = $mk('Test Head of Department', Role::Hod);
        $this->subject = Subject::create(['code' => 'CSC101', 'name' => 'Intro', 'hod_id' => $this->hod->id]);
        $this->responsible($this->examiner, $this->subject, Role::Examiner);
        $this->responsible($this->internal, $this->subject, Role::InternalModerator);
    }

    private function responsible(User $u, Subject $s, Role $r): void
    {
        \Illuminate\Support\Facades\DB::table('subject_user')->insertOrIgnore(['user_id' => $u->id, 'subject_id' => $s->id, 'role' => $r->value]);
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
            'period' => 'first', 'year' => 2026, 'heqf_level' => 6, 'subject_level' => 'Y2',
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

    private function newAssessment(bool $withExternal = true, ?Subject $subject = null, string $number = 'T1'): Assessment
    {
        $subject ??= $this->subject;
        $this->as($this->examiner)->post('/assessments', ['year_level' => 'Y1', 
            'subject_id' => $subject->id, 'number' => $number,
            'internal_moderator_id' => $this->internal->id, 'external_moderator_id' => $withExternal ? $this->external->id : null, 'year_level' => 'Y2',
        ])->assertRedirect();

        return Assessment::where('subject_id', $subject->id)->where('number', $number)->firstOrFail();
    }

    private function submitSection1(Assessment $a): void
    {
        $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'PAPER', 'file' => $this->pdf('paper.pdf')], ['Accept' => 'application/json'])->assertOk();
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
            ->assertStatus(400)->assertJsonPath('error', 'Upload the assessment paper first (the paper includes the memorandum).');
        $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'PAPER', 'file' => UploadedFile::fake()->createWithContent('evil.pdf', 'MZ not a pdf')], ['Accept' => 'application/json'])->assertStatus(400);
        $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'PAPER', 'file' => $this->pdf('paper.pdf')], ['Accept' => 'application/json'])->assertOk();
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
        $this->as($this->internal)->post('/assessments', ['year_level' => 'Y1', 'subject_id' => $this->subject->id, 'number' => 'X', 'internal_moderator_id' => $this->internal->id])->assertForbidden();
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
        $this->responsible($this->examiner, $subject, Role::Examiner);
        $this->responsible($this->internal, $subject, Role::InternalModerator);
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

    public function test_subject_import_my_subjects_and_responsibility_rules(): void
    {
        $this->as($this->hod);
        $r = app(\App\Services\SubjectImporter::class)->import(database_path('data/Subjects.xlsx'), $this->hod);
        $this->assertGreaterThan(70, $r['created']);
        $this->assertSame('BEMC ECP, BEMC1', Subject::where('code', 'EMR101S')->value('qualification')); // offered in two qualifications, one subject
        $again = app(\App\Services\SubjectImporter::class)->import(database_path('data/Subjects.xlsx'), $this->hod);
        $this->assertSame(0, $again['created']);

        $phe = Subject::where('code', 'PHE261S')->firstOrFail();
        $other = Subject::where('code', 'PHE262S')->firstOrFail();
        $this->as($this->examiner)->put('/my-subjects', ['subjects' => ['EXAMINER' => [$phe->id]]])->assertRedirect();
        $this->assertTrue($this->examiner->isResponsibleFor($phe, Role::Examiner));
        $this->as($this->examiner)->get('/assessments/create')->assertOk()->assertSee('PHE261S')->assertDontSee('PHE262S');
        // only subjects you are responsible for
        $this->as($this->examiner)->post('/assessments', ['year_level' => 'Y1', 'subject_id' => $other->id, 'number' => 'T1', 'internal_moderator_id' => $this->internal->id])->assertSessionHas('error');
        // moderators who registered for the subject are the only valid choice
        $this->responsible($this->internal, $phe, Role::InternalModerator);
        $second = User::create(['name' => 'Other Mod', 'email' => 'm2@t.test', 'role' => Role::InternalModerator, 'password' => self::PW]);
        $this->as($this->examiner)->post('/assessments', ['year_level' => 'Y1', 'subject_id' => $phe->id, 'number' => 'T1', 'internal_moderator_id' => $second->id])->assertSessionHas('error');
        $this->as($this->examiner)->post('/assessments', ['year_level' => 'Y1', 'subject_id' => $phe->id, 'number' => 'T1', 'internal_moderator_id' => $this->internal->id, 'assessment_date' => '2026-11-20'])->assertRedirect();
        $this->as($this->external)->put('/my-subjects', ['subjects' => []])->assertForbidden();
    }

    public function test_deadlines_follow_the_assessment_date_and_remind_people(): void
    {
        $a = $this->newAssessment();
        $a->update(['assessment_date' => now('UTC')->addDays(20)->toDateString()]);
        $d = $a->fresh()->deadline();
        $this->assertSame('Pre-moderation', $d['phase']);
        $this->assertSame(6, $d['days']); // 20 - 14
        $this->assertSame('ok', $d['state']);
        $a->update(['assessment_date' => now('UTC')->addDays(15)->toDateString()]);
        $this->assertSame('soon', $a->fresh()->deadline()['state']); // due in 1 day
        $this->assertSame(1, app(\App\Services\DeadlineReminders::class)->run());
        $this->assertSame(0, app(\App\Services\DeadlineReminders::class)->run()); // once a day
        $this->assertStringContainsString('Reminder', \App\Models\Notification::where('user_id', $this->examiner->id)->latest('id')->value('message'));
        // after the assessment the post-moderation window applies; overdue also alerts the HOD
        $a->update(['assessment_date' => now('UTC')->subDays(20)->toDateString(), 'status' => AssessmentStatus::PendingFinalModeration]);
        $d = $a->fresh()->deadline();
        $this->assertSame(['Post-moderation', 'overdue'], [$d['phase'], $d['state']]);
        $this->assertSame(-6, $d['days']);
        $this->assertSame(2, app(\App\Services\DeadlineReminders::class)->run()); // internal moderator + HOD
        $this->as($this->internal)->get('/')->assertOk()->assertSee('overdue');
        $this->as($this->internal)->get("/assessments/{$a->id}")->assertOk()->assertSee('Post-moderation by');
    }

    public function test_password_protected_word_papers_are_accepted(): void
    {
        $a = $this->newAssessment();
        $file = new UploadedFile(base_path('docs/samples/PHE261S_Test1_paper_PASSWORD-demo123.docx'), 'protected.docx', null, null, true);
        $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'PAPER', 'file' => $file], ['Accept' => 'application/json'])->assertOk();
        // an ordinary legacy Word binary is still not a .docx
        $fake = UploadedFile::fake()->createWithContent('old.docx', "\xD0\xCF\x11\xE0 just an old .doc");
        $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'MEMO', 'file' => $fake], ['Accept' => 'application/json'])->assertStatus(400);
    }

    public function test_assessment_number_is_one_of_t1_to_t7(): void
    {
        $this->as($this->examiner)->get('/assessments/create')->assertOk()->assertSee('Tests match their T column')->assertSee('>T7<', false);
        foreach (['Test 1', 'T8', 't1', ''] as $bad) {
            $this->as($this->examiner)->post('/assessments', ['year_level' => 'Y1', 'subject_id' => $this->subject->id, 'number' => $bad, 'internal_moderator_id' => $this->internal->id])->assertSessionHasErrors('number');
        }
        foreach (['', 'YR 1', 'Y5'] as $bad) {
            $this->as($this->examiner)->post('/assessments', ['year_level' => $bad, 'subject_id' => $this->subject->id, 'number' => 'T6', 'internal_moderator_id' => $this->internal->id])->assertSessionHasErrors('year_level');
        }
        $this->as($this->examiner)->post('/assessments', ['year_level' => 'Y1', 'subject_id' => $this->subject->id, 'number' => 'T7', 'internal_moderator_id' => $this->internal->id])->assertRedirect();
        $this->assertSame('CSC101 · T7', Assessment::firstOrFail()->title());
        $this->assertSame('Y1', Assessment::firstOrFail()->year_level);
        $this->as($this->examiner)->get('/assessments/'.Assessment::firstOrFail()->id)->assertOk()->assertSee('Year level');
    }

    public function test_a_hidden_record_shows_a_helpful_404(): void
    {
        $a = $this->newAssessment();
        $this->as($this->external)->get("/assessments/{$a->id}")->assertNotFound()->assertSee('Test External Moderator')->assertSee('not visible to');
    }

    public function test_staff_register_and_the_hod_approves(): void
    {
        $this->get('/register')->assertOk()->assertSee('Register for an account')->assertDontSee('Head of Department</b>', false);
        $this->get('/login')->assertOk()->assertSee('Register for an account');
        $good = ['name' => 'Nia New', 'email' => 'Nia@t.test', 'department' => 'EMC', 'roles' => ['EXAMINER', 'INTERNAL_MODERATOR'], 'password' => 'long-enough-pw', 'password_confirmation' => 'long-enough-pw'];
        // head of department can never be self-requested; weak or mismatched passwords are refused
        $this->post('/register', ['roles' => ['HOD']] + $good)->assertSessionHasErrors('roles.0');
        $this->post('/register', ['password' => 'short', 'password_confirmation' => 'short'] + $good)->assertSessionHasErrors('password');
        $this->post('/register', ['password_confirmation' => 'different-one'] + $good)->assertSessionHasErrors('password');
        $this->post('/register', $good)->assertRedirect('/login');
        $u = User::where('email', 'nia@t.test')->firstOrFail();
        $this->assertFalse($u->active);
        $this->assertSame('pending', $u->approval_status);
        $this->assertSame('Examiner · Internal Moderator', $u->roleLabels());
        $this->assertTrue(\App\Models\Notification::where('user_id', $this->hod->id)->where('message', 'like', '%Nia New%')->exists());
        $this->post('/register', $good)->assertSessionHasErrors('email'); // no duplicates
        // cannot sign in yet, and is told why
        $this->post('/login', ['email' => 'nia@t.test', 'password' => 'long-enough-pw'])->assertSessionHas('error', fn ($m) => str_contains($m, 'waiting for approval'));
        $this->assertGuest();
        // only the HOD sees and decides; an examiner cannot
        $this->as($this->examiner)->post("/admin/registrations/{$u->id}/approve", ['roles' => ['EXAMINER']])->assertForbidden();
        $this->as($this->hod)->get('/admin')->assertOk()->assertSee('Registrations waiting for approval')->assertSee('Nia New');
        $this->as($this->hod)->post("/admin/registrations/{$u->id}/approve", [])->assertSessionHasErrors('roles');
        $this->as($this->hod)->post("/admin/registrations/{$u->id}/approve", ['roles' => ['EXAMINER']])->assertRedirect();
        $u->refresh();
        $this->assertTrue($u->active);
        $this->assertSame('approved', $u->approval_status);
        $this->assertSame('Examiner', $u->roleLabels()); // the HOD may change what was asked for
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => 'nia@t.test', 'password' => 'long-enough-pw'])->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
    }

    public function test_a_rejected_registration_is_removed_and_domains_can_be_restricted(): void
    {
        config(['dems.register_domains' => 'cput.ac.za']);
        $base = ['name' => 'Ray Reject', 'roles' => ['EXAMINER'], 'password' => 'long-enough-pw', 'password_confirmation' => 'long-enough-pw'];
        $this->post('/register', ['email' => 'ray@gmail.com'] + $base)->assertSessionHasErrors('email');
        $this->post('/register', ['email' => 'ray@cput.ac.za'] + $base)->assertRedirect('/login');
        $u = User::where('email', 'ray@cput.ac.za')->firstOrFail();
        $this->as($this->hod)->delete("/admin/registrations/{$u->id}")->assertRedirect();
        $this->assertNull(User::where('email', 'ray@cput.ac.za')->first());
        $this->as($this->hod)->delete("/admin/registrations/{$this->examiner->id}")->assertNotFound(); // only pending ones
    }

    public function test_purge_removes_only_the_demo_accounts_and_their_records(): void
    {
        $demo = User::create(['name' => 'Sample Examiner', 'email' => 'examiner@example.edu', 'role' => Role::Examiner, 'password' => self::PW]);
        $this->responsible($demo, $this->subject, Role::Examiner);
        $sample = Subject::create(['code' => 'CSC101X', 'name' => 'keep', 'hod_id' => $this->hod->id]);
        $this->as($demo)->post('/assessments', ['year_level' => 'Y1', 'subject_id' => $this->subject->id, 'number' => 'T1', 'internal_moderator_id' => $this->internal->id]);
        $this->assertSame(1, Assessment::count());
        $this->artisan('dems:purge-demo', ['--force' => true])->assertSuccessful();
        $this->assertNull(User::where('email', 'examiner@example.edu')->first());
        $this->assertSame(0, Assessment::count());
        $this->assertNotNull(User::find($this->examiner->id)); // real staff untouched
        $this->assertNotNull(Subject::find($sample->id));
    }

    public function test_examiner_plans_test_dates_and_is_reminded_three_days_before(): void
    {
        $other = Subject::create(['code' => 'OTH101', 'name' => 'Not mine', 'hod_id' => $this->hod->id]);
        $d = fn (int $n) => now('UTC')->addDays($n)->toDateString();
        $this->as($this->examiner)->put('/my-subjects', [
            'subjects' => ['EXAMINER' => [$this->subject->id]],
            'plans' => [$this->subject->id => ['T1' => $d(10), 'T2' => $d(3), 'T3' => $d(1), 'T9' => $d(2), 'T4' => ''], $other->id => ['T1' => $d(2)]],
        ])->assertRedirect();
        // only real tests of subjects they examine, with a date
        $this->assertSame(['T1', 'T2', 'T3'], \App\Models\AssessmentPlan::where('user_id', $this->examiner->id)->orderBy('number')->pluck('number')->all());
        // T2 (3 days) and T3 (tomorrow) are inside the window; T1 (10 days) is not
        $this->assertSame(2, app(\App\Services\DeadlineReminders::class)->run());
        $msgs = \App\Models\Notification::where('user_id', $this->examiner->id)->pluck('message')->implode(' | ');
        $this->assertStringContainsString('CSC101 T2 is written in 3 days', $msgs);
        $this->assertStringContainsString('CSC101 T3 is written tomorrow', $msgs);
        $this->assertStringContainsString('not been started', $msgs);
        $this->assertStringNotContainsString('T1', $msgs);
        $this->assertSame(0, app(\App\Services\DeadlineReminders::class)->run()); // once a day
        // the planned date flows into the assessment and the dashboard lists what is coming
        $this->as($this->examiner)->get('/assessments/create')->assertOk();
        $this->as($this->examiner)->post('/assessments', ['year_level' => 'Y1', 'subject_id' => $this->subject->id, 'number' => 'T2', 'internal_moderator_id' => $this->internal->id])->assertRedirect();
        $this->assertSame($d(3), \Carbon\Carbon::parse(Assessment::firstOrFail()->assessment_date)->toDateString());
        $this->as($this->examiner)->get('/')->assertOk()->assertSee('Upcoming assessments')->assertSee('CSC101 · T1')->assertSee('Start in the system');
        $this->as($this->external)->put('/my-subjects', [])->assertForbidden();
    }

    public function test_examiner_can_set_a_default_internal_moderator_per_subject(): void
    {
        $ok = $this->internal;
        $stranger = User::create(['name' => 'Not Registered', 'email' => 'nr@t.test', 'role' => Role::InternalModerator, 'password' => self::PW]);
        // a moderator is registered for the subject, so only they may be the default; an examiner is never their own moderator
        $this->as($this->examiner)->put('/my-subjects', ['subjects' => ['EXAMINER' => [$this->subject->id]], 'moderators' => [$this->subject->id => $stranger->id]])->assertRedirect();
        $this->assertNull(\Illuminate\Support\Facades\DB::table('subject_user')->where('user_id', $this->examiner->id)->where('role', 'EXAMINER')->value('default_internal_moderator_id'));
        $this->as($this->examiner)->put('/my-subjects', ['subjects' => ['EXAMINER' => [$this->subject->id]], 'moderators' => [$this->subject->id => $ok->id]])->assertRedirect();
        $this->assertSame($ok->id, (int) \Illuminate\Support\Facades\DB::table('subject_user')->where('user_id', $this->examiner->id)->where('role', 'EXAMINER')->value('default_internal_moderator_id'));
        $this->as($this->examiner)->get('/my-subjects')->assertOk()->assertSee('Internal moderator for this subject')->assertSee($ok->name);
        $this->as($this->examiner)->get('/assessments/create')->assertOk()->assertSee('\\u0022'.$this->subject->id.'\\u0022:'.$ok->id, false);
    }

    public function test_supplementary_and_special_assessments_and_yearly_repeats(): void
    {
        $base = ['year_level' => 'Y1', 'subject_id' => $this->subject->id, 'internal_moderator_id' => $this->internal->id];
        $go = fn (array $x) => $this->as($this->examiner)->post('/assessments', $base + $x);
        $this->as($this->examiner)->get('/assessments/create')->assertOk()->assertSee('Supplementary assessment')->assertSee('Special assessment');
        $go(['number' => 'T1', 'academic_year' => 2026])->assertRedirect();
        // the same test may run again in another academic year, but not twice in one
        $go(['number' => 'T1', 'academic_year' => 2027])->assertRedirect();
        $go(['number' => 'T1', 'academic_year' => 2026])->assertSessionHas('error');
        // supplementary and special sittings, with a note to tell several sittings apart
        $go(['number' => 'SUPP', 'academic_year' => 2026])->assertRedirect();
        $go(['number' => 'SUPP', 'academic_year' => 2026])->assertSessionHas('error');
        $go(['number' => 'SPEC', 'academic_year' => 2026, 'label' => 'Aegrotat group A'])->assertRedirect();
        $go(['number' => 'SPEC', 'academic_year' => 2026, 'label' => 'Aegrotat group B'])->assertRedirect();
        $go(['number' => 'SPEC', 'academic_year' => 2026, 'label' => 'Aegrotat group B'])->assertSessionHas('error');
        $go(['number' => 'T9'])->assertSessionHasErrors('number');
        $supp = Assessment::where('number', 'SUPP')->firstOrFail();
        $this->assertSame('CSC101 · Supplementary', $supp->title());
        $this->assertSame('CSC101 · Special (Aegrotat group A)', Assessment::where('label', 'Aegrotat group A')->firstOrFail()->title());
        $this->assertSame(['T3'], Assessment::make(['number' => 'T3'])->marksHeaders());
        $this->assertContains('SUPPLEMENTARY', $supp->marksHeaders());
        $this->as($this->examiner)->get("/assessments/{$supp->id}")->assertOk()->assertSee('Supplementary');
        // the class-list reader offers supplementary / special columns next to T1…
        foreach (['T1', 'T7', 'SUPP', 'Supplementary', 'SPEC', 'Special assessment'] as $h) {
            $this->assertSame(1, preg_match(config('dems.marks_column_pattern'), $h), $h);
        }
        $this->assertSame(0, preg_match(config('dems.marks_column_pattern'), 'S_NAME'));
    }

    public function test_seeder_creates_the_hod_and_can_reset_a_forgotten_password(): void
    {
        $_ENV['DEMS_SEED_EMAIL'] = $_SERVER['DEMS_SEED_EMAIL'] = 'boss@t.test'; $_ENV['DEMS_SEED_PASSWORD'] = $_SERVER['DEMS_SEED_PASSWORD'] = 'first-password-1';
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
        $boss = User::where('email', 'boss@t.test')->firstOrFail();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('first-password-1', $boss->password));
        // changing the variable alone does not change an existing account…
        $_ENV['DEMS_SEED_PASSWORD'] = $_SERVER['DEMS_SEED_PASSWORD'] = 'second-password-2';
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('first-password-1', $boss->fresh()->password));
        // …the reset switch does
        $_ENV['DEMS_SEED_RESET_PASSWORD'] = $_SERVER['DEMS_SEED_RESET_PASSWORD'] = 'true';
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('second-password-2', $boss->fresh()->password));
        unset($_ENV['DEMS_SEED_EMAIL'], $_SERVER['DEMS_SEED_EMAIL']); unset($_ENV['DEMS_SEED_PASSWORD'], $_SERVER['DEMS_SEED_PASSWORD']); unset($_ENV['DEMS_SEED_RESET_PASSWORD'], $_SERVER['DEMS_SEED_RESET_PASSWORD']);
    }

    public function test_accounts_with_capitals_in_the_email_can_sign_in(): void
    {
        $this->as($this->hod)->post('/admin/users', ['name' => 'John', 'email' => ' Meyerjo@CPUT.ac.za ', 'roles' => ['HOD'], 'password' => 'a-long-password-1'])->assertRedirect();
        $this->assertSame('meyerjo@cput.ac.za', User::where('name', 'John')->value('email'));
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => 'Meyerjo@cput.ac.za', 'password' => 'a-long-password-1'])->assertRedirect('/');
        $this->app['auth']->forgetGuards();
        // a duplicate that differs only by case is refused
        $this->as($this->hod)->post('/admin/users', ['name' => 'Jon', 'email' => 'MEYERJO@cput.ac.za', 'roles' => ['EXAMINER'], 'password' => 'a-long-password-1'])->assertSessionHasErrors('email');
    }

    public function test_a_signed_in_person_can_change_their_password(): void
    {
        $this->get('/account/password')->assertRedirect('/login'); // not for visitors
        $url = '/account/password';
        $this->as($this->examiner)->get($url)->assertOk()->assertSee('Change password');
        $ok = ['current_password' => self::PW, 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'];
        $this->as($this->examiner)->post($url, ['current_password' => 'wrong'] + $ok)->assertSessionHasErrors('current_password');
        $this->as($this->examiner)->post($url, ['password' => 'short', 'password_confirmation' => 'short'] + $ok)->assertSessionHasErrors('password');
        $this->as($this->examiner)->post($url, ['password_confirmation' => 'different-one'] + $ok)->assertSessionHasErrors('password');
        $this->as($this->examiner)->post($url, ['password' => self::PW, 'password_confirmation' => self::PW] + $ok)->assertSessionHasErrors('password'); // must differ
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check(self::PW, $this->examiner->fresh()->password));
        $this->as($this->examiner)->post($url, $ok)->assertRedirect($url)->assertSessionHas('status');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('brand-new-pass-1', $this->examiner->fresh()->password));
        $this->assertTrue(\App\Models\AuditLog::where('action', 'PASSWORD_CHANGED')->where('user_id', $this->examiner->id)->exists());
        // the old password no longer signs in, the new one does
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => $this->examiner->email, 'password' => self::PW])->assertSessionHas('error');
        $this->post('/login', ['email' => $this->examiner->email, 'password' => 'brand-new-pass-1'])->assertRedirect(); // back to the page they first asked for
    }

    public function test_purge_also_removes_the_sample_hod_once_a_real_one_exists(): void
    {
        $sample = User::create(['name' => 'Head of Department', 'email' => 'hod@example.edu', 'role' => Role::Hod, 'password' => self::PW]);
        $mine = Subject::create(['code' => 'IMP100', 'name' => 'Imported under the sample HOD', 'hod_id' => $sample->id]);
        $this->artisan('dems:purge-demo', ['--force' => true])->assertSuccessful();
        $this->assertNull(User::where('email', 'hod@example.edu')->first());
        $this->assertSame($this->hod->id, $mine->fresh()->hod_id); // handed to the real HOD
        // with no other HOD the sample one is left alone
        $only = User::create(['name' => 'Head of Department', 'email' => 'hod@example.edu', 'role' => Role::Hod, 'password' => self::PW]);
        User::where('id', $this->hod->id)->update(['active' => false]);
        $this->artisan('dems:purge-demo', ['--force' => true])->assertSuccessful();
        $this->assertNotNull(User::find($only->id));
    }

    public function test_purge_never_deletes_a_real_examiners_assessment(): void
    {
        $demoMod = User::create(['name' => 'Sample Moderator', 'email' => 'moderator@example.edu', 'role' => Role::InternalModerator, 'password' => self::PW]);
        $this->responsible($demoMod, $this->subject, Role::InternalModerator);
        $this->as($this->examiner)->post('/assessments', ['year_level' => 'Y1', 'subject_id' => $this->subject->id, 'number' => 'T1', 'internal_moderator_id' => $demoMod->id]);
        $this->assertSame(1, Assessment::count());
        $this->artisan('dems:purge-demo', ['--force' => true])->assertSuccessful();
        $this->assertSame(1, Assessment::count()); // the real examiner's record is untouched
        $this->assertFalse((bool) $demoMod->fresh()->active); // the sample moderator is switched off, so it no longer appears in pickers
        $this->as($this->examiner)->get('/assessments/create')->assertOk()->assertDontSee('Sample Moderator');
        $this->artisan('dems:purge-demo', ['--force' => true])->assertSuccessful(); // safe to run on every deploy
        $this->assertSame(1, Assessment::count());
    }

    public function test_the_hod_can_switch_an_account_off_and_on(): void
    {
        $this->as($this->hod)->get('/admin')->assertOk()->assertSee('Switch account off');
        $this->as($this->hod)->patch("/admin/users/{$this->internal->id}/active")->assertRedirect()->assertSessionHas('status');
        $this->assertFalse((bool) $this->internal->fresh()->active);
        // switched off: cannot sign in, and is no longer offered as a moderator
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => $this->internal->email, 'password' => self::PW])->assertSessionHas('error');
        $this->as($this->examiner)->get('/assessments/create')->assertOk()->assertDontSee($this->internal->name);
        $this->as($this->hod)->patch("/admin/users/{$this->internal->id}/active")->assertRedirect();
        $this->assertTrue((bool) $this->internal->fresh()->active);
        // guard rails: not yourself, not the last HOD, and only the HOD may do it
        $this->as($this->hod)->patch("/admin/users/{$this->hod->id}/active")->assertSessionHas('error');
        $this->assertTrue((bool) $this->hod->fresh()->active);
        $this->as($this->examiner)->patch("/admin/users/{$this->internal->id}/active")->assertForbidden();
        $second = User::create(['name' => 'Second HOD', 'email' => 'h2@t.test', 'role' => Role::Hod, 'password' => self::PW]);
        $this->as($second)->patch("/admin/users/{$this->hod->id}/active")->assertSessionHas('status'); // fine: another HOD remains
        $this->as($this->hod)->patch("/admin/users/{$second->id}/active")->assertSessionHas('error'); // …but the last active HOD is protected
    }

    public function test_the_site_is_installable_as_an_app(): void
    {
        foreach (['manifest.json', 'sw.js', 'offline.html', 'icons/apple-touch-icon.png', 'icons/icon-192.png', 'icons/icon-512.png', 'icons/icon-maskable-512.png'] as $f) {
            $this->assertFileExists(public_path($f), $f);
        }
        $m = json_decode(file_get_contents(public_path('manifest.json')), true);
        $this->assertSame('standalone', $m['display']);
        $this->assertSame('/', $m['start_url']);
        $this->get('/login')->assertOk()->assertSee('rel="manifest"', false)->assertSee('apple-touch-icon', false)->assertSee('apple-mobile-web-app-capable', false)->assertSee('viewport-fit=cover', false);
        $this->as($this->examiner)->get('/')->assertOk()->assertSee('rel="manifest"', false)->assertSee('apple-touch-icon', false);
        // the service worker must never cache signed-in pages or data
        $sw = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("mode !== 'navigate'", $sw);
        $this->assertStringNotContainsString('cache.put', $sw);
    }

    public function test_app_store_requirements_privacy_support_delete_account_and_push_registration(): void
    {
        // public pages Apple requires
        $this->get('/privacy')->assertOk()->assertSee('Privacy notice')->assertSee('Delete my account');
        $this->get('/support')->assertOk()->assertSee('Support');
        $this->get('/login')->assertOk()->assertSee('Privacy')->assertSee('Support');
        // push registration: signed-in only, one row per token, the last person to sign in on a phone owns it
        $tok = str_repeat('ab', 32);
        $this->postJson('/devices', ['token' => $tok])->assertUnauthorized();
        $this->as($this->examiner)->postJson('/devices', ['token' => $tok, 'platform' => 'ios'])->assertOk();
        $this->as($this->internal)->postJson('/devices', ['token' => $tok])->assertOk();
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('device_tokens')->where('token', $tok)->count());
        $this->assertSame($this->internal->id, (int) \Illuminate\Support\Facades\DB::table('device_tokens')->where('token', $tok)->value('user_id'));
        $this->as($this->examiner)->postJson('/devices', ['token' => 'short'])->assertUnprocessable();
        // account deletion: wrong password, unfinished work and the last HOD are refused
        $this->as($this->examiner)->get('/account/password')->assertOk()->assertSee('Delete my account')->assertSee('Face ID lock');
        $a = $this->newAssessment();
        $this->as($this->examiner)->delete('/account', ['current_password' => self::PW, 'confirm_delete' => 1])->assertSessionHas('error');
        $this->as($this->hod)->delete('/account', ['current_password' => self::PW, 'confirm_delete' => 1])->assertSessionHas('error'); // last HOD
        $this->as($this->external)->delete('/account', ['current_password' => 'wrong', 'confirm_delete' => 1])->assertSessionHasErrors('current_password');
        $this->as($this->external)->delete('/account', ['current_password' => self::PW])->assertSessionHasErrors('confirm_delete');
        // a person with nothing in progress can delete: anonymised, signed out, cannot sign in again
        $spare = User::create(['name' => 'Spare Moderator', 'email' => 'spare@t.test', 'role' => Role::ExternalModerator, 'password' => self::PW]);
        $this->as($spare)->postJson('/devices', ['token' => str_repeat('cd', 32)]);
        $id = $spare->id; $mail = $spare->email;
        $this->as($spare)->delete('/account', ['current_password' => self::PW, 'confirm_delete' => 1])->assertRedirect('/login');
        $u = User::find($id);
        $this->assertSame('Former user', $u->name);
        $this->assertFalse((bool) $u->active);
        $this->assertStringStartsWith('deleted-', $u->email);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('device_tokens')->where('user_id', $id)->count());
        $this->assertGuest();
        $this->post('/login', ['email' => $mail, 'password' => self::PW])->assertSessionHas('error');
        $this->assertTrue(\App\Models\AuditLog::where('action', 'ACCOUNT_DELETED')->exists());
    }

    public function test_push_notifications_are_sent_to_the_persons_iphones(): void
    {
        \Illuminate\Support\Facades\Http::fake(['nothing.example/*' => \Illuminate\Support\Facades\Http::response('', 200)]);
        $tok = str_repeat('ab', 32); $bad = str_repeat('cd', 32);
        \Illuminate\Support\Facades\DB::table('device_tokens')->insert([['user_id' => $this->examiner->id, 'token' => $tok, 'platform' => 'ios', 'created_at' => now(), 'updated_at' => now()], ['user_id' => $this->examiner->id, 'token' => $bad, 'platform' => 'ios', 'created_at' => now(), 'updated_at' => now()]]);
        // not configured: nothing is sent
        \App\Models\Notification::create(['user_id' => $this->examiner->id, 'message' => 'Hello']);
        \Illuminate\Support\Facades\Http::assertNothingSent();
        // configured with a real P-256 key
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($k, $pem); $pub = openssl_pkey_get_details($k)['key'];
        config(['dems.apns' => ['key_id' => 'KEY123', 'team_id' => 'TEAM456', 'bundle_id' => 'za.test.app', 'key' => $pem, 'production' => true]]);
        \Illuminate\Support\Facades\Http::fake(['api.push.apple.com/3/device/'.$bad => \Illuminate\Support\Facades\Http::response(['reason' => 'BadDeviceToken'], 400), 'api.push.apple.com/*' => \Illuminate\Support\Facades\Http::response('', 200)]);
        $a = $this->newAssessment();
        \App\Models\Notification::create(['user_id' => $this->examiner->id, 'assessment_id' => $a->id, 'message' => 'Revision requested on CSC101.']);
        \Illuminate\Support\Facades\Http::assertSent(function ($r) use ($tok, $a) {
            return $r->url() === "https://api.push.apple.com/3/device/{$tok}" && $r->hasHeader('apns-topic', 'za.test.app') && $r->hasHeader('apns-push-type', 'alert')
                && $r['aps']['alert']['body'] === 'Revision requested on CSC101.' && $r['url'] === '/assessments/'.$a->id && str_starts_with($r->header('authorization')[0], 'bearer ');
        });
        // the rejected token is removed, the good one stays
        $this->assertSame([$tok], \Illuminate\Support\Facades\DB::table('device_tokens')->pluck('token')->all());
        // the provider token is a valid ES256 JWT for that key
        [$h, $c, $sig] = explode('.', app(\App\Services\PushSender::class)->jwt());
        $dec = fn ($x) => base64_decode(strtr($x, '-_', '+/'));
        $this->assertSame(['alg' => 'ES256', 'kid' => 'KEY123'], json_decode($dec($h), true));
        $this->assertSame('TEAM456', json_decode($dec($c), true)['iss']);
        $raw = $dec($sig); $this->assertSame(64, strlen($raw));
        $int = fn (string $x) => (ord($x[0]) > 127 ? "\0" : '').ltrim($x, "\0");
        $r = $int(substr($raw, 0, 32)); $s = $int(substr($raw, 32));
        $der = "\x30".chr(4 + strlen($r) + strlen($s))."\x02".chr(strlen($r)).$r."\x02".chr(strlen($s)).$s;
        $this->assertSame(1, openssl_verify("{$h}.{$c}", $der, $pub, OPENSSL_ALGO_SHA256));
    }

    public function test_login_and_pages_render_for_every_stage(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back');
        $this->post('/login', ['email' => 'examiner@t.test', 'password' => 'bad'])->assertRedirect()->assertSessionHas('error');
        $this->post('/login', ['email' => 'examiner@t.test', 'password' => self::PW])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Welcome back, Test');
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
        $this->responsible($this->examiner, $phe, Role::Examiner);
        $this->responsible($this->internal, $phe, Role::InternalModerator);
        $a = $this->newAssessment(false, $phe, 'T2');
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

    public function test_system_administrator_manages_accounts_but_sees_no_assessments(): void
    {
        $sa = User::create(['name' => 'Sys Admin', 'email' => 'sa@t.test', 'role' => Role::SystemAdmin, 'password' => self::PW]);
        $a = $this->newAssessment();

        $this->as($sa)->get('/')->assertRedirect('/admin');
        $this->as($sa)->get('/admin')->assertOk()->assertSee('System Administrator');
        $this->as($sa)->get("/assessments/{$a->id}")->assertNotFound();

        // a system administrator can hand out the role; a HOD can neither see nor grant it
        $this->as($sa)->patch("/admin/users/{$this->examiner->id}/roles", ['roles' => ['EXAMINER', 'SYSTEM_ADMIN']])->assertSessionHasNoErrors();
        $this->assertTrue($this->examiner->fresh()->hasRole(Role::SystemAdmin));
        $this->as($this->hod)->get('/admin')->assertOk()->assertDontSee('value="SYSTEM_ADMIN"', false);
        $this->as($this->hod)->patch("/admin/users/{$this->internal->id}/roles", ['roles' => ['INTERNAL_MODERATOR', 'SYSTEM_ADMIN']])->assertSessionHasErrors('roles.1');
        $this->assertFalse($this->internal->fresh()->hasRole(Role::SystemAdmin));
        // ...and cannot demote one
        $this->as($this->hod)->patch("/admin/users/{$this->examiner->id}/roles", ['roles' => ['EXAMINER']])->assertSessionHas('error');
        $this->assertTrue($this->examiner->fresh()->hasRole(Role::SystemAdmin));
    }

    public function test_seeder_creates_system_administrator_from_environment(): void
    {
        foreach (['DEMS_ADMIN_EMAIL' => 'Boss@T.test', 'DEMS_ADMIN_PASSWORD' => 'a-long-password', 'DEMS_ADMIN_NAME' => 'Boss'] as $k => $v) {
            $_ENV[$k] = $_SERVER[$k] = $v;
        }
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $u = User::where('email', 'boss@t.test')->first();
        $this->assertNotNull($u);
        $this->assertTrue($u->hasRole(Role::SystemAdmin));
        foreach (['DEMS_ADMIN_EMAIL', 'DEMS_ADMIN_PASSWORD', 'DEMS_ADMIN_NAME'] as $k) {
            unset($_ENV[$k], $_SERVER[$k]);
        }
    }

    public function test_hod_overview_filters_and_system_admin_has_no_access(): void
    {
        $this->subject->update(['qualification' => 'Diploma in Testing']);
        $bare = Subject::create(['code' => 'BAR101', 'name' => 'Bare', 'qualification' => 'Diploma in Testing, BTech Other', 'hod_id' => $this->hod->id]);
        $this->newAssessment();

        $this->as($this->hod)->get('/overview')->assertOk()->assertSee('Diploma in Testing')->assertSee('BTech Other')->assertSee('BAR101')->assertSee('CSC101');
        $this->as($this->hod)->get('/overview?qualification=BTech+Other')->assertOk()->assertSee('BAR101')->assertDontSee('CSC101');
        $this->as($this->hod)->get('/overview?coverage=no_moderator')->assertOk()->assertSee('BAR101')->assertDontSee('CSC101');
        $this->as($this->hod)->get('/overview?staff='.$this->internal->id.'&staff_role=INTERNAL_MODERATOR')->assertOk()->assertSee('CSC101')->assertDontSee('BAR101');
        $this->as($this->hod)->get('/overview/subjects/'.$this->subject->id)->assertOk()->assertSee('Test Examiner');
        Assessment::query()->update(['assessment_date' => now('UTC')->addDays(5)->toDateString()]);
        $this->as($this->hod)->get('/overview')->assertOk()->assertSee('CSC101');
        $this->as($this->hod)->get('/overview/subjects/'.$this->subject->id)->assertOk()->assertSee('written');
        $this->as($this->examiner)->get('/overview')->assertForbidden();

        $sa = User::create(['name' => 'Sys Admin', 'email' => 'sa2@t.test', 'role' => Role::SystemAdmin, 'password' => self::PW]);
        $this->as($sa)->get('/overview')->assertForbidden();
        $this->as($sa)->get('/overview/subjects/'.$this->subject->id)->assertForbidden();
    }

    public function test_staff_upload_creates_accounts_and_reports_problems(): void
    {
        $sa = User::create(['name' => 'Sys Admin', 'email' => 'sa3@t.test', 'role' => Role::SystemAdmin, 'password' => self::PW]);
        $csv = "Name,Email,Roles,Department\nAnn Lee,ANN@T.test,\"Examiner, Internal Moderator\",Eng\nBo Hod,bo@t.test,HOD,Eng\nCy Root,cy@t.test,System Administrator,IT\nDup,examiner@t.test,Examiner,x\nBad,bad@t.test,Wizard,x\n";
        $file = UploadedFile::fake()->createWithContent('staff.csv', $csv);

        // a HOD may upload staff and HODs but not System Administrators
        $this->as($this->hod)->post('/admin/users/import', ['file' => $file, 'password' => 'temporary-pass-1'])->assertSessionHas('status');
        $ann = User::where('email', 'ann@t.test')->first();
        $this->assertTrue($ann->hasRole(Role::Examiner) && $ann->hasRole(Role::InternalModerator));
        $this->assertTrue(User::where('email', 'bo@t.test')->first()->hasRole(Role::Hod));
        $this->assertNull(User::where('email', 'cy@t.test')->first());
        $this->assertNull(User::where('email', 'bad@t.test')->first());
        $this->assertSame(2, collect(session('import_errors'))->count());

        // a System Administrator may
        $this->as($sa)->post('/admin/users/import', ['file' => UploadedFile::fake()->createWithContent('s.csv', $csv), 'password' => 'temporary-pass-1'])->assertSessionHas('status');
        $this->assertTrue(User::where('email', 'cy@t.test')->first()->hasRole(Role::SystemAdmin));
        $this->post('/login', ['email' => 'ann@t.test', 'password' => 'temporary-pass-1'])->assertRedirect();
    }

    public function test_email_notifications_respect_opt_out_and_test_button(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $n = app(\App\Services\Notifier::class);
        $n->emailUsers([$this->examiner->id, $this->internal->id], 'Hello', 'Body', 'https://x.test');
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\Notice::class, 2);

        $this->as($this->internal)->post('/account/notifications', ['email_notifications' => '0'])->assertSessionHas('status');
        $this->assertFalse($this->internal->fresh()->email_notifications);
        \Illuminate\Support\Facades\Mail::fake();
        $n->emailUsers([$this->examiner->id, $this->internal->id], 'Hello', 'Body');
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\Notice::class, 1);

        config(['mail.default' => 'smtp']);
        $this->as($this->hod)->post('/admin/test-email')->assertSessionHas('status');
        config(['mail.default' => 'log']);
        $this->as($this->hod)->post('/admin/test-email')->assertSessionHas('error');
        $this->as($this->examiner)->post('/admin/test-email')->assertForbidden();
    }

    public function test_pre_moderation_submission_reaches_the_internal_moderator(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $a = $this->newAssessment();
        $this->submitSection1($a);

        $this->assertSame(AssessmentStatus::PendingPreModeration, $a->fresh()->status);
        $this->assertSame($this->internal->id, $a->fresh()->internal_moderator_id);
        $this->assertTrue(\App\Models\Notification::where('user_id', $this->internal->id)->where('assessment_id', $a->id)->where('message', 'like', '%pre-moderation review%')->exists());
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\Notice::class, fn ($m) => $m->hasTo($this->internal->email));
        $this->as($this->internal)->get('/')->assertOk()->assertSee('CSC101')->assertSee('Your turn');
        $this->as($this->internal)->get("/assessments/{$a->id}")->assertOk();
        $this->as($this->internal)->getJson('/notifications')->assertJsonPath('unread', \App\Models\Notification::where('user_id', $this->internal->id)->where('read', false)->count());
    }

    public function test_several_documents_no_memo_needed_and_historic_dates(): void
    {
        $a = $this->newAssessment();
        $up = fn ($name, $label, $file) => $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'PAPER', 'label' => $label, 'file' => $this->pdf($file)], ['Accept' => 'application/json'])->assertOk();

        // a paper alone (it contains the memo) is enough; several documents are listed under their names
        $up('p', 'Exam paper', 'paper.pdf');
        $up('a', 'Annexure A', 'annex.pdf');
        $a->load('attachments');
        $this->assertSame(['Exam paper', 'Annexure A'], $a->documents()->map->doc_label->all());

        $this->as($this->examiner)->get("/assessments/{$a->id}")->assertOk()->assertSee('Annexure A')->assertSee('no separate memo is needed', false);

        // the same name replaces; removing drops it, and the file stays on record
        $up('p2', 'exam paper', 'paper2.pdf');
        $a->load('attachments');
        $this->assertCount(2, $a->documents());
        $annex = $a->documents()->firstWhere('label', 'Annexure A');
        $this->as($this->examiner)->deleteJson("/assessments/{$a->id}/attachments/{$annex->id}")->assertOk();
        $a->load('attachments');
        $this->assertSame(['exam paper'], $a->documents()->map(fn ($d) => mb_strtolower($d->doc_label))->all());
        $this->assertSame(3, $a->attachments->count());
        $this->as($this->internal)->deleteJson("/assessments/{$a->id}/attachments/{$annex->id}")->assertForbidden();

        // an assessment written in the past is accepted and can be submitted
        $a->update(['assessment_date' => '2023-03-12']);
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => array_merge($this->s1(), ['assessment_date' => '2023-03-12', 'year' => 2023]), 'signature' => $this->sig()])->assertOk();
        $a->refresh();
        $this->assertSame(AssessmentStatus::PendingPreModeration, $a->status);
        $this->assertSame('2023-03-12', $a->assessment_date->toDateString());
        $this->as($this->internal)->get("/assessments/{$a->id}")->assertOk()->assertSee('exam paper', false);
        // documents can no longer be removed once submitted
        $doc = $a->fresh()->load('attachments')->documents()->first();
        $this->as($this->examiner)->deleteJson("/assessments/{$a->id}/attachments/{$doc->id}")->assertStatus(409);
    }

    public function test_submit_needs_at_least_one_document(): void
    {
        $a = $this->newAssessment();
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => $this->s1(), 'signature' => $this->sig()])->assertStatus(400);
    }

    public function test_pdf_comments_are_anchored_to_a_page_and_stay_with_their_document(): void
    {
        $a = $this->newAssessment();
        $up = fn ($label, $file) => $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'PAPER', 'label' => $label, 'file' => $this->pdf($file)], ['Accept' => 'application/json'])->assertOk()->json('attachment.id');
        $paper = $up('Exam paper', 'p.pdf');
        $annex = $up('Annexure A', 'a.pdf');
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => $this->s1(), 'signature' => $this->sig()])->assertOk();

        $this->as($this->internal)->postJson("/attachments/{$paper}/comments", ['body' => 'Too easy for level 6.', 'quote' => 'Explain the difference', 'start_offset' => 40, 'page' => 2])->assertCreated()->assertJsonPath('page', 2);
        $this->as($this->internal)->postJson("/attachments/{$paper}/comments", ['body' => 'Whole page needs a rewrite.', 'page' => 3])->assertCreated();
        $this->as($this->internal)->postJson("/attachments/{$paper}/comments", ['body' => 'bad', 'page' => 0])->assertStatus(422);

        $this->as($this->internal)->getJson("/attachments/{$paper}/comments")->assertOk()->assertJsonCount(2, 'comments')->assertJsonPath('comments.0.page', 2);
        // comments on one document do not appear on another document of the same assessment
        $this->as($this->internal)->getJson("/attachments/{$annex}/comments")->assertOk()->assertJsonCount(0, 'comments');
        // the PDF is shown with the page viewer
        $this->as($this->internal)->get("/assessments/{$a->id}")->assertOk()->assertSee('pdfReview', false);
    }

    public function test_examiner_and_moderator_reply_to_each_other_under_a_comment(): void
    {
        $a = $this->newAssessment();
        $paper = $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'PAPER', 'label' => 'Exam paper', 'file' => $this->pdf('p.pdf')], ['Accept' => 'application/json'])->json('attachment.id');
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => $this->s1(), 'signature' => $this->sig()])->assertOk();
        $cid = $this->as($this->internal)->postJson("/attachments/{$paper}/comments", ['body' => 'Q2 is too easy.', 'page' => 1, 'quote' => 'Q2', 'start_offset' => 3])->assertCreated()->json('id');

        // the examiner cannot reply while the record is with the moderator; the moderator can
        $this->as($this->examiner)->postJson("/comments/{$cid}/replies", ['body' => 'Not allowed yet'])->assertForbidden();
        $this->as($this->internal)->postJson("/comments/{$cid}/replies", ['body' => 'Please see the memo too.'])->assertCreated();
        $this->as($this->external)->postJson("/comments/{$cid}/replies", ['body' => 'x'])->assertStatus(404);

        $this->as($this->internal)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'REVISION_REQUESTED', 'comments' => 'See comments.'])->assertOk();
        $this->as($this->examiner)->postJson("/comments/{$cid}/replies", ['body' => 'Made it harder, see Q2 again.'])->assertCreated();
        $this->as($this->examiner)->postJson("/comments/{$cid}/replies", ['body' => 'x'])->assertStatus(422);

        $r = $this->as($this->examiner)->getJson("/attachments/{$paper}/comments")->assertOk()->assertJsonPath('can.reply', true);
        $this->assertSame(['Please see the memo too.', 'Made it harder, see Q2 again.'], collect($r->json('comments.0.replies'))->pluck('body')->all());
        $this->assertTrue(\App\Models\Notification::where('user_id', $this->internal->id)->where('message', 'like', '%replied to a comment%')->exists());
        $this->assertTrue(\App\Models\Notification::where('user_id', $this->examiner->id)->where('message', 'like', '%replied to a comment%')->exists());
    }

    public function test_revised_uploads_become_versions_and_the_approved_one_is_final(): void
    {
        $a = $this->newAssessment();
        $up = fn ($file) => $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'PAPER', 'label' => 'Exam paper', 'file' => $this->pdf($file)], ['Accept' => 'application/json'])->assertOk()->json('attachment.version');
        $this->assertSame(1, $up('v1.pdf'));
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => $this->s1(), 'signature' => $this->sig()])->assertOk();
        $this->as($this->internal)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'REVISION_REQUESTED', 'comments' => 'Fix Q2.'])->assertOk();
        $this->assertSame(2, $up('v2.pdf'));
        $this->assertSame(3, $up('v3.pdf'));

        $a = $a->fresh('attachments');
        $docs = $a->documents();
        $this->assertCount(1, $docs);
        $this->assertSame(3, $a->versionNumber($docs->first()));
        $this->assertSame('current', $a->versionState($docs->first()));
        $this->assertSame('superseded', $a->versionState($a->versionsOf($docs->first())->first()));
        $this->as($this->examiner)->get("/assessments/{$a->id}")->assertOk()->assertSee('version\\u0022:3', false);

        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => $this->s1(), 'signature' => $this->sig()])->assertOk();
        $this->approveGate1($a);
        $a = $a->fresh('attachments');
        $this->assertSame('final', $a->versionState($a->documents()->first()));
        $doc = $a->documents()->first();
        $this->actingAs($this->internal);
        $this->blade('<x-doc-viewer :docs="$docs" />', ['docs' => [[$doc->doc_label, $doc]]])->assertSee('Version 3')->assertSee('Version 1')->assertSee('Superseded')->assertSee('Final');
        $this->assertSame(3, collect(app(\App\Services\ReportService::class)->data($a)['docs'] ?? [])->count() ? 3 : 0);
    }

    public function test_head_of_programme_registers_is_approved_with_qualifications_and_sees_only_their_dashboard(): void
    {
        $this->subject->update(['qualification' => 'Diploma in Testing']);
        $other = Subject::create(['code' => 'OTH101', 'name' => 'Other', 'qualification' => 'BTech Elsewhere', 'hod_id' => $this->hod->id]);
        $this->newAssessment();

        $this->post('/logout');
        // anyone may ask for the role when registering, with a note about their qualification
        $this->post('/register', ['name' => 'Pat Programme', 'email' => 'pat@t.test', 'roles' => ['HEAD_OF_PROGRAMME'], 'programme_request' => 'Diploma in Testing', 'password' => 'long-enough-pass', 'password_confirmation' => 'long-enough-pass'])->assertRedirect('/login');
        $pat = User::where('email', 'pat@t.test')->first();
        $this->assertSame('Diploma in Testing', $pat->programme_request);
        $this->assertFalse($pat->active);

        // approval needs the qualification to be assigned
        $this->as($this->hod)->get('/admin')->assertOk()->assertSee('Head of Programme')->assertSee('Diploma in Testing');
        $this->as($this->hod)->post("/admin/registrations/{$pat->id}/approve", ['roles' => ['HEAD_OF_PROGRAMME']])->assertSessionHasErrors('roles');
        $this->as($this->hod)->post("/admin/registrations/{$pat->id}/approve", ['roles' => ['HEAD_OF_PROGRAMME'], 'programmes' => ['Diploma in Testing', 'Not a real qualification']])->assertSessionHasNoErrors();
        $pat->refresh();
        $this->assertTrue($pat->active && $pat->hasRole(Role::HeadOfProgramme));
        $this->assertSame(['Diploma in Testing'], $pat->programme_qualifications);

        // lands on the programme dashboard and sees only that qualification
        $this->post('/logout');
        $this->post('/login', ['email' => 'pat@t.test', 'password' => 'long-enough-pass'])->assertRedirect('/overview');
        $this->as($pat)->get('/')->assertRedirect('/overview');
        $this->as($pat)->get('/overview')->assertOk()->assertSee('Head of Programme')->assertSee('Diploma in Testing')->assertSee('CSC101')->assertDontSee('OTH101')->assertDontSee('BTech Elsewhere')->assertDontSee('Open →');
        $this->as($pat)->get('/overview/subjects/'.$this->subject->id)->assertOk()->assertSee('Test Examiner')->assertDontSee('Open →');
        $this->as($pat)->get('/overview/subjects/'.$other->id)->assertNotFound();

        // no access to assessments, files, the admin area or the audit trail
        $a = Assessment::first();
        $this->as($pat)->get("/assessments/{$a->id}")->assertNotFound();
        $this->as($pat)->get('/admin')->assertForbidden();
        $this->as($pat)->get('/assessments/create')->assertForbidden();
    }

    public function test_staff_upload_can_create_a_head_of_programme_with_qualification(): void
    {
        $this->subject->update(['qualification' => 'Diploma in Testing']);
        $csv = "Name,Email,Roles,Qualifications\nHop One,hop1@t.test,Head of Programme,Diploma in Testing\nHop Two,hop2@t.test,Head of Programme,\n";
        $this->as($this->hod)->post('/admin/users/import', ['file' => UploadedFile::fake()->createWithContent('s.csv', $csv), 'password' => 'temporary-pass-1'])->assertSessionHas('status');
        $this->assertSame(['Diploma in Testing'], User::where('email', 'hop1@t.test')->first()->programme_qualifications);
        $this->assertNull(User::where('email', 'hop2@t.test')->first());
        $this->assertStringContainsString('Head of Programme needs', collect(session('import_errors'))->implode(' '));
    }

    public function test_qualification_code_and_date_travel_through_every_section(): void
    {
        $csv = "Qualification,Qualification Code,Subject Code,Subject Name\nNational Diploma: Testing,NDTEST,TST101,Testing One\nBTech: Testing,BTTEST,TST101,Testing One\n";
        $this->as($this->hod)->post('/admin/subjects/import', ['file' => UploadedFile::fake()->createWithContent('s.csv', $csv)])->assertSessionHasNoErrors();
        $subj = Subject::where('code', 'TST101')->first();
        $this->assertSame('National Diploma: Testing, BTech: Testing', $subj->qualification);
        $this->assertSame([['name' => 'National Diploma: Testing', 'code' => 'NDTEST'], ['name' => 'BTech: Testing', 'code' => 'BTTEST']], $subj->qualificationOptions());
        $this->responsible($this->examiner, $subj, Role::Examiner);

        // the examiner picks which qualification this sitting belongs to; the date is set once
        $this->as($this->examiner)->post('/assessments', ['subject_id' => $subj->id, 'number' => 'T2', 'internal_moderator_id' => $this->internal->id, 'qualification' => 'BTech: Testing', 'assessment_date' => '2026-05-20', 'year_level' => 'Y2'])->assertRedirect();
        $a = Assessment::where('subject_id', $subj->id)->first();
        $this->assertSame('BTech: Testing', $a->qualification);
        $this->assertSame('BTTEST', $a->qualification_code);

        // header on every page, and Section 1 is pre-filled so nothing is typed twice
        $this->as($this->examiner)->get("/assessments/{$a->id}")->assertOk()->assertSee('TST101')->assertSee('BTech: Testing')->assertSee('BTTEST')->assertSee('20 May 2026')
            ->assertSee('qualification_code\u0022:\u0022BTTEST', false)->assertSee('assessment_date\u0022:\u00222026-05-20', false);

        // the report falls back to the record when Section 1 holds nothing for these fields
        $a->update(['s1_examiner' => ['weights' => []]]);
        $data = app(\App\Services\ReportService::class)->data($a->fresh());
        $this->assertSame('BTech: Testing', $data['s1e']['qualification'] ?? $data['s1_examiner']['qualification'] ?? null);
    }

    public function test_admin_sets_qualification_codes_and_records_follow(): void
    {
        $this->subject->update(['qualification' => 'National Diploma: Emergency Medical Care, BTech: Emergency Medical Care']);
        $a = $this->newAssessment();
        $a->update(['qualification' => 'National Diploma: Emergency Medical Care']);
        $this->as($this->hod)->get('/admin')->assertOk()->assertSee('Qualification codes')->assertSee('National Diploma: Emergency Medical Care');

        $this->as($this->hod)->post('/admin/qualifications', ['names' => ['National Diploma: Emergency Medical Care', 'BTech: Emergency Medical Care'], 'codes' => ['d2emca', 'BPEMCA']])->assertSessionHas('status');
        $this->assertSame('D2EMCA, BPEMCA', $this->subject->fresh()->qualification_code);
        $this->assertSame('D2EMCA', $a->fresh()->qualification_code);
        $this->assertSame('BPEMCA', collect($this->subject->fresh()->qualificationOptions())->firstWhere('name', 'BTech: Emergency Medical Care')['code']);
        $this->as($this->examiner)->post('/admin/qualifications', ['names' => ['x'], 'codes' => ['y']])->assertForbidden();
    }

    public function test_subminimum_from_calendar_to_report_pass_fail_figures(): void
    {
        // set in the examiner's test calendar (Yes + %), copied to the assessment when it is started
        $this->as($this->examiner)->put('/my-subjects', ['subjects' => ['EXAMINER' => [$this->subject->id]], 'plans' => [$this->subject->id => ['T1' => '2026-03-12']],
            'subon' => [$this->subject->id => ['T1' => '1']], 'subpct' => [$this->subject->id => ['T1' => '60']]])->assertSessionHasNoErrors();
        $this->as($this->examiner)->get('/my-subjects')->assertOk()->assertSee('Subminimum');
        $a = $this->newAssessment(false);
        $this->assertTrue($a->subminimum);
        $this->assertEquals(60, $a->subminimum_percent);

        // Yes needs a percentage in Section 1
        $this->as($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'PAPER', 'file' => $this->pdf('paper.pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => array_merge($this->s1(), ['subminimum' => 'YES', 'subminimum_percent' => null]), 'signature' => $this->sig()])->assertStatus(422);
        $this->as($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['s1' => array_merge($this->s1(), ['subminimum' => 'YES', 'subminimum_percent' => 60]), 'signature' => $this->sig()])->assertOk();
        $this->approveGate1($a);
        $this->submitSection2Manual($a); // marks 72, 48.5, 55, 30

        $r = $a->fresh()->resultsSummary();
        $this->assertSame(4, $r['total']);
        $this->assertSame([2, 2], [$r['passed'], $r['failed']]);
        $this->assertSame([1, 3, 1], [$r['met'], $r['not_met'], $r['passed_but_below']]);
        $this->assertStringContainsString('1 of 4 students met the subminimum and 3 did not (1 reached the pass mark but not the subminimum)', $r['text']);

        // the moderator sees it next to the adjustments question; the report carries it
        $this->as($this->internal)->get("/assessments/{$a->id}")->assertOk()->assertSee('Subminimum 60%')->assertSee('2 of 4 students passed');
        $this->approveGate2($a);
        $data = app(\App\Services\ReportService::class)->data($a->fresh());
        $this->assertSame($r['text'], $data['results']['text']);
        $html = view('pdf.report', $data)->render();
        $this->assertStringContainsString('Subminimum 60%: 1 of 4 students met the subminimum', $html);

        // without a subminimum only the pass/fail line appears
        $b = $this->newAssessment(false, null, 'T2');
        $this->assertFalse($b->subminimum);
    }

    public function test_hod_can_delete_a_completed_practice_record(): void
    {
        $a = $this->newAssessment(false);
        $this->submitSection1($a);
        $this->approveGate1($a);
        $this->submitSection2Manual($a);
        $this->approveGate2($a);
        $a->refresh();
        $this->assertSame(AssessmentStatus::Completed, $a->status);
        $keys = $a->attachments->pluck('storage_key')->all();
        $this->assertNotEmpty($keys);

        // only the HOD of the subject, with the password and the word DELETE
        $this->as($this->examiner)->delete("/assessments/{$a->id}")->assertForbidden();
        $this->as($this->internal)->delete("/assessments/{$a->id}")->assertForbidden();
        $this->as($this->hod)->get("/assessments/{$a->id}")->assertOk()->assertSee('Delete this record');
        $this->as($this->hod)->delete("/assessments/{$a->id}", ['current_password' => self::PW, 'confirm_text' => 'nope'])->assertSessionHasErrors('confirm_text');
        $this->as($this->hod)->delete("/assessments/{$a->id}", ['current_password' => 'wrong-password-1', 'confirm_text' => 'DELETE'])->assertSessionHasErrors('current_password');
        $this->assertNotNull(Assessment::find($a->id));
        $otherHod = User::create(['name' => 'Other HOD', 'email' => 'oh@t.test', 'role' => Role::Hod, 'password' => self::PW]);
        $this->as($otherHod)->delete("/assessments/{$a->id}", ['current_password' => self::PW, 'confirm_text' => 'DELETE'])->assertStatus(404);

        $this->as($this->hod)->delete("/assessments/{$a->id}", ['current_password' => self::PW, 'confirm_text' => 'DELETE'])->assertRedirect(route('overview.subject', $a->subject_id));
        $this->assertNull(Assessment::find($a->id));
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('attachments')->where('assessment_id', $a->id)->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('audit_logs')->where('assessment_id', $a->id)->count());
        foreach ($keys as $k) {
            $this->assertSame(0, \Illuminate\Support\Facades\DB::table('file_blobs')->where('id', explode(':', $k, 2)[1])->count());
        }
        // one line stays in the general log, and the people on the record are told
        $log = \App\Models\AuditLog::where('action', 'ASSESSMENT_DELETED')->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->details['was_completed']);
        $this->assertTrue(\App\Models\Notification::where('user_id', $this->examiner->id)->where('message', 'like', '%deleted the record%')->exists());
        $this->assertFalse(\App\Models\Notification::where('user_id', $this->hod->id)->where('message', 'like', '%deleted the record%')->exists());
    }

    public function test_sick_and_deferred_assessments_are_available_like_tests(): void
    {
        $this->assertContains('SICK', config('dems.assessment_numbers'));
        $this->assertContains('DEFER', config('dems.assessment_numbers'));
        $this->as($this->examiner)->get('/assessments/create')->assertOk()->assertSee('Sick assessment')->assertSee('Deferred assessment');
        $this->as($this->examiner)->get('/my-subjects')->assertOk()->assertSee('Sick')->assertSee('Deferred');

        $this->as($this->examiner)->put('/my-subjects', ['subjects' => ['EXAMINER' => [$this->subject->id]], 'plans' => [$this->subject->id => ['SICK' => '2026-07-01', 'DEFER' => '2026-07-08']]])->assertSessionHasNoErrors();
        $this->assertSame(2, \App\Models\AssessmentPlan::where('user_id', $this->examiner->id)->whereIn('number', ['SICK', 'DEFER'])->count());

        $sick = $this->newAssessment(false, null, 'SICK');
        $def = $this->newAssessment(false, null, 'DEFER');
        $this->assertSame('Sick', $sick->number_label);
        $this->assertSame('Deferred', $def->number_label);
        $this->assertSame('2026-07-08', $def->assessment_date->toDateString()); // picked up from the calendar
        $this->assertContains('DEFERRED', $def->marksHeaders());
        $this->assertContains('AEGROTAT', $sick->marksHeaders());
        $this->assertSame(1, preg_match(config('dems.marks_column_pattern'), 'Deferred'));
        $this->assertSame(1, preg_match(config('dems.marks_column_pattern'), 'Sick test'));
    }

    public function test_dean_report_page_pdf_and_word_for_the_hod_only(): void
    {
        $this->subject->update(['qualification' => 'Diploma in Testing']);
        Subject::create(['code' => 'BAR101', 'name' => 'Bare', 'qualification' => 'Diploma in Testing', 'hod_id' => $this->hod->id]); // no examiner or moderator
        $a = $this->newAssessment(false);
        $a->update(['assessment_date' => '2026-03-12', 'academic_year' => 2026]);
        $this->submitSection1($a);
        $this->approveGate1($a);
        $this->submitSection2Manual($a);
        $this->approveGate2($a);
        $b = $this->newAssessment(false, null, 'T2');
        $b->update(['assessment_date' => '2020-01-01']);

        $page = $this->as($this->hod)->get('/dean-report')->assertOk();
        $page->assertSee('Report for the Dean')->assertSee('At a glance')->assertSee('Moderation compliance')->assertSee('Assessment quality')->assertSee('Matters for attention')
            ->assertSee('BAR101')->assertSee('CSC101')->assertSee('Diploma in Testing')->assertSee('Without an internal moderator');
        $this->as($this->hod)->get('/dean-report?year=2026&kind=tests&to_whom=Prof+Dean&comment=Good+semester')->assertOk()->assertSee('Prof Dean')->assertSee('Good semester');
        $this->as($this->hod)->get('/dean-report?qualification=Nothing+here')->assertOk()->assertSee('No assessments match these filters');

        // PDF
        $pdf = $this->as($this->hod)->get('/dean-report/pdf?year=2026');
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        // Word
        $docx = $this->as($this->hod)->get('/dean-report/docx');
        $docx->assertOk();
        $this->assertStringContainsString('wordprocessingml', $docx->headers->get('Content-Type'));
        $tmp = tempnam(sys_get_temp_dir(), 'd').'.docx';
        file_put_contents($tmp, $docx->getContent());
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $xml = $zip->getFromName('word/document.xml');
        $this->assertStringContainsString('Assessment moderation overview', $xml);
        $this->assertStringContainsString('CSC101', $xml);
        $zip->close();
        @unlink($tmp);
        $this->assertSame(2, \App\Models\AuditLog::where('action', 'DEAN_REPORT_DOWNLOADED')->count());

        // only the HOD
        foreach (['/dean-report', '/dean-report/pdf', '/dean-report/docx'] as $url) {
            $this->as($this->examiner)->get($url)->assertForbidden();
            $this->as($this->internal)->get($url)->assertForbidden();
        }
        $sa = User::create(['name' => 'SA', 'email' => 'sa9@t.test', 'role' => Role::SystemAdmin, 'password' => self::PW]);
        $this->as($sa)->get('/dean-report')->assertForbidden();
    }

    public function test_dean_report_narrative_with_groq_or_plain_draft_flows_into_page_and_word(): void
    {
        $a = $this->newAssessment(false);
        $a->update(['assessment_date' => '2026-03-12', 'academic_year' => 2026]);
        $this->submitSection1($a);
        $this->approveGate1($a);
        $this->submitSection2Manual($a);
        $this->as($this->examiner)->get('/'); // keep examiner session warm
        \App\Models\Assessment::query()->update(['s2_examiner' => ['registered' => 30, 'answers' => ['q5' => 'Teach with ana.lee@cput.ac.za examples; Mr Hidden Name will help.']]]);

        // plain draft needs no AI
        $draft = $this->as($this->hod)->postJson('/dean-report/narrative', ['mode' => 'draft'])->assertOk()->json('sections');
        $this->assertSame(array_keys(\App\Services\DeanNarrative::SECTIONS), array_keys($draft));
        $this->assertStringContainsString('1 subject and 1 assessment', $draft['summary']);

        // AI without a key says what to do
        config(['dems.groq.key' => null]);
        $this->as($this->hod)->postJson('/dean-report/narrative', ['mode' => 'ai'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'GROQ_API_KEY'));

        // AI with a key: Groq is called with figures only (no staff names, no e-mail addresses)
        config(['dems.groq.key' => 'test-key', 'dems.groq.model' => 'test-model']);
        \Illuminate\Support\Facades\Http::fake(['api.groq.com/*' => \Illuminate\Support\Facades\Http::sequence()->push(['choices' => [['message' => ['content' => json_encode([
            'summary' => 'The department moderated one assessment this period.', 'compliance' => 'Compliance paragraph.', 'quality' => 'Quality paragraph.', 'results' => 'Results paragraph.', 'attention' => 'Attention paragraph.', 'actions' => 'Actions paragraph.'])]]]])->push(['error' => ['message' => 'rate']], 429)]);
        $out = $this->as($this->hod)->postJson('/dean-report/narrative', ['mode' => 'ai'])->assertOk()->json('sections');
        $this->assertSame('The department moderated one assessment this period.', $out['summary']);
        \Illuminate\Support\Facades\Http::assertSent(function ($req) {
            $body = json_encode($req->data());
            return $req->hasHeader('Authorization', 'Bearer test-key') && $req['model'] === 'test-model'
                && ! str_contains($body, 'Test Examiner') && ! str_contains($body, 'Test Internal') && ! str_contains($body, 'ana.lee@cput.ac.za') && str_contains($body, 'CSC101');
        });
        // a Groq failure is reported, not thrown
        $this->as($this->hod)->postJson('/dean-report/narrative', ['mode' => 'ai'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'busy'));

        // save, then the page and the Word file carry the text
        $this->as($this->hod)->postJson('/dean-report/narrative/save', ['sections' => $out])->assertOk();
        $this->as($this->hod)->get('/dean-report')->assertOk()->assertSee('The department moderated one assessment this period.')->assertSee('A narrative is included');
        $docx = $this->as($this->hod)->get('/dean-report/docx')->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'n').'.docx';
        file_put_contents($tmp, $docx->getContent());
        $zip = new \ZipArchive();
        $zip->open($tmp);
        $this->assertStringContainsString('Quality paragraph.', $zip->getFromName('word/document.xml'));
        $zip->close();
        @unlink($tmp);
        $this->assertStringStartsWith('%PDF', $this->as($this->hod)->get('/dean-report/pdf')->getContent());
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'DEAN_NARRATIVE_GENERATED')->count());

        // remove it again; others cannot use the endpoints
        $this->as($this->hod)->postJson('/dean-report/narrative/save', ['sections' => []])->assertOk();
        $this->as($this->hod)->get('/dean-report')->assertDontSee('A narrative is included');
        $this->as($this->examiner)->postJson('/dean-report/narrative', ['mode' => 'draft'])->assertForbidden();
        $this->as($this->examiner)->postJson('/dean-report/narrative/save', ['sections' => $out])->assertForbidden();
    }
}
