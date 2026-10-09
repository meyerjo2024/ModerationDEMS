<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\AttachmentKind;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Subject;
use App\Models\User;
use App\Services\Audit;
use App\Services\FileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

    private function rows(int $mcq = 40): array
    {
        return [
            ['type' => 'Multiple choice', 'weighting' => $mcq, 'heqf_level' => 6, 'aligned' => true, 'comment' => ''],
            ['type' => 'Essay', 'weighting' => 100 - $mcq, 'heqf_level' => 7, 'aligned' => true, 'comment' => 'L7'],
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

    private function checklist(string $answer = 'YES'): array
    {
        return collect(array_keys(config('dems.quality_checks')))->map(fn ($id) => ['id' => $id, 'answer' => $answer, 'comment' => ''])->all();
    }

    private function newAssessment(bool $withExternal = true): Assessment
    {
        $this->actingAs($this->examiner)->post('/assessments', [
            'subject_id' => $this->subject->id, 'number' => 'Test 1',
            'internal_moderator_id' => $this->internal->id, 'external_moderator_id' => $withExternal ? $this->external->id : null,
        ])->assertRedirect();

        return Assessment::firstOrFail();
    }

    private function submitSection1(Assessment $a): void
    {
        $this->actingAs($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'PAPER', 'file' => $this->pdf('paper.pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'MEMO', 'file' => $this->pdf('memo.pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['question_types' => $this->rows(), 'signature' => $this->sig()])->assertOk();
    }

    public function test_full_lifecycle_with_external_moderator_produces_signed_pdf(): void
    {
        $a = $this->newAssessment();
        $this->assertSame(AssessmentStatus::Draft, $a->status);

        // Phase 1 guards
        $this->actingAs($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['question_types' => $this->rows(), 'signature' => $this->sig()])
            ->assertStatus(400)->assertJsonPath('error', 'Upload the draft assessment paper first.');
        $this->actingAs($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'PAPER', 'file' => UploadedFile::fake()->createWithContent('evil.pdf', 'MZ not a pdf')], ['Accept' => 'application/json'])
            ->assertStatus(400);
        $this->actingAs($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'PAPER', 'file' => $this->pdf('paper.pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'MEMO', 'file' => $this->pdf('memo.pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['question_types' => $this->rows(40 + 10), 'signature' => $this->sig()])->assertStatus(200); // 50+50 = 100
        $this->assertSame(AssessmentStatus::PendingPreModeration, $a->fresh()->status);
        $this->actingAs($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['question_types' => $this->rows(), 'signature' => $this->sig()])->assertStatus(409); // no double submit

        // Gate 1: consensus required, then a revision loop, then approval
        $this->actingAs($this->internal)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'APPROVED', 'consensus' => false, 'signature' => $this->sig()])->assertStatus(400);
        $this->actingAs($this->internal)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'REVISION_REQUESTED', 'comments' => 'Question 2 is above level 7.'])->assertOk();
        $this->assertSame(AssessmentStatus::RevisionRequested, $a->fresh()->status);
        $this->actingAs($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['question_types' => $this->rows(), 'signature' => $this->sig()])->assertOk();
        $this->actingAs($this->internal)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'APPROVED', 'consensus' => true, 'signature' => $this->sig('wrong')])->assertStatus(403);
        $this->actingAs($this->internal)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'APPROVED', 'consensus' => true, 'comments' => 'Good.', 'signature' => $this->sig()])->assertOk();
        $this->assertSame(AssessmentStatus::ReadyForPostModeration, $a->fresh()->status);

        // Phase 3: workbook → statistics
        $att = $this->actingAs($this->examiner)->post("/assessments/{$a->id}/attachments", ['kind' => 'MARKS', 'file' => $this->xlsx()], ['Accept' => 'application/json'])->assertOk()->json('attachment.id');
        $sheets = $this->actingAs($this->examiner)->getJson("/assessments/{$a->id}/marks?attachment={$att}")->assertOk()->json('sheets');
        $t1 = collect($sheets[0]['columns'])->firstWhere('header', 'T1');
        $this->assertNotNull($t1);
        $this->assertSame(19, $t1['nonBlank']);
        $source = ['type' => 'excel', 'attachment_id' => $att, 'sheet' => 'Marks', 'column' => $t1['index']];
        $prev = $this->actingAs($this->examiner)->postJson("/assessments/{$a->id}/calculate", ['source' => $source, 'total_marks' => 100])->assertOk()->json('stats');
        $this->assertSame(18, $prev['candidate_count']);
        $this->assertSame(1, $prev['invalid_entries']);
        $this->assertEquals(72.22, $prev['pass_rate']);
        $this->assertEquals(90, $prev['highest_mark']);
        $this->assertArrayNotHasKey('scores', $prev); // raw scores are never sent to the browser
        $this->actingAs($this->examiner)->postJson("/assessments/{$a->id}/submit-post", ['source' => $source, 'total_marks' => 100, 'commentary' => 'Essay Q2 was poorly answered.', 'signature' => $this->sig()])->assertOk();
        $a->refresh();
        $this->assertSame(AssessmentStatus::PendingFinalModeration, $a->status);
        $this->assertSame(18, $a->candidate_count);

        // Gate 2 → Gate 3 → completion
        $this->actingAs($this->internal)->postJson("/assessments/{$a->id}/final-review", ['decision' => 'APPROVED', 'consensus' => true, 'scripts_sampled' => 5, 'checklist' => $this->checklist('NO'), 'signature' => $this->sig()])->assertStatus(400); // "No" needs a comment
        $this->actingAs($this->internal)->postJson("/assessments/{$a->id}/final-review", ['decision' => 'APPROVED', 'consensus' => true, 'scripts_sampled' => 5, 'checklist' => $this->checklist(), 'comments' => 'Consistent.', 'signature' => $this->sig()])->assertOk();
        $this->assertSame(AssessmentStatus::PendingExternalModeration, $a->fresh()->status);
        $this->actingAs($this->external)->postJson("/assessments/{$a->id}/final-review", ['decision' => 'APPROVED', 'consensus' => true, 'scripts_sampled' => 3, 'checklist' => $this->checklist(), 'signature' => $this->sig()])->assertOk();

        $a->refresh();
        $this->assertSame(AssessmentStatus::Completed, $a->status);
        $this->assertNotNull($a->completed_at);
        $report = Attachment::where('assessment_id', $a->id)->where('kind', AttachmentKind::FinalReport->value)->firstOrFail();
        $bytes = app(FileStore::class)->get($report->storage_key);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame(hash('sha256', $bytes), $report->sha256);
        $this->assertTrue(app(Audit::class)->verify($a->id), 'audit hash chain is intact');

        // tampering is detected
        AuditLog::where('assessment_id', $a->id)->where('action', 'SECTION2_SUBMITTED')->update(['action' => 'SOMETHING_ELSE']);
        $this->assertFalse(app(Audit::class)->verify($a->id));

        // the report can be downloaded by the HOD, the marks workbook only by the examiner
        $this->actingAs($this->hod)->get("/files/{$report->id}")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->hod)->get("/files/{$att}")->assertForbidden();
        $this->actingAs($this->examiner)->get("/files/{$att}")->assertOk();
        // completed is final
        $this->actingAs($this->examiner)->postJson("/assessments/{$a->id}/submit-pre", ['question_types' => $this->rows(), 'signature' => $this->sig()])->assertStatus(409);
    }

    public function test_completes_at_gate_2_when_no_external_moderator_is_assigned(): void
    {
        $a = $this->newAssessment(false);
        $this->submitSection1($a);
        $this->actingAs($this->internal)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'APPROVED', 'consensus' => true, 'signature' => $this->sig()])->assertOk();
        $this->actingAs($this->examiner)->postJson("/assessments/{$a->id}/submit-post", [
            'source' => ['type' => 'manual', 'text' => "72\n48.5\n55\n30"], 'total_marks' => 100, 'commentary' => 'Reasonable spread.', 'signature' => $this->sig(),
        ])->assertOk();
        $this->actingAs($this->internal)->postJson("/assessments/{$a->id}/final-review", ['decision' => 'APPROVED', 'consensus' => true, 'scripts_sampled' => 2, 'checklist' => $this->checklist(), 'signature' => $this->sig()])->assertOk();
        $this->assertSame(AssessmentStatus::Completed, $a->fresh()->status);
        $this->assertNotNull($a->fresh()->attachments->firstWhere('kind', AttachmentKind::FinalReport));
    }

    public function test_final_review_can_be_returned_to_the_examiner(): void
    {
        $a = $this->newAssessment(false);
        $this->submitSection1($a);
        $this->actingAs($this->internal)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'APPROVED', 'consensus' => true, 'signature' => $this->sig()])->assertOk();
        $this->actingAs($this->examiner)->postJson("/assessments/{$a->id}/submit-post", ['source' => ['type' => 'manual', 'text' => "60\n70"], 'total_marks' => 100, 'commentary' => 'Fine overall results.', 'signature' => $this->sig()])->assertOk();
        $this->actingAs($this->internal)->postJson("/assessments/{$a->id}/final-review", ['decision' => 'REVISION_REQUESTED', 'comments' => 'Please recheck the totals.'])->assertOk();
        $this->assertSame(AssessmentStatus::ReadyForPostModeration, $a->fresh()->status);
    }

    public function test_access_control(): void
    {
        $a = $this->newAssessment();
        // moderators cannot create assessments; examiners cannot review
        $this->actingAs($this->internal)->post('/assessments', ['subject_id' => $this->subject->id, 'number' => 'X', 'internal_moderator_id' => $this->internal->id])->assertForbidden();
        $this->actingAs($this->examiner)->postJson("/assessments/{$a->id}/pre-review", ['decision' => 'REVISION_REQUESTED', 'comments' => 'nope nope'])->assertForbidden();
        // the external moderator can't even see a record that hasn't been handed to them
        $this->actingAs($this->external)->get("/assessments/{$a->id}")->assertNotFound();
        $this->actingAs($this->internal)->get("/assessments/{$a->id}")->assertOk();
        $this->actingAs($this->hod)->get("/assessments/{$a->id}")->assertOk();
        // other examiners can't see it either
        $other = User::create(['name' => 'Other', 'email' => 'o@t.test', 'role' => Role::Examiner, 'password' => self::PW]);
        $this->actingAs($other)->get("/assessments/{$a->id}")->assertNotFound();
        // only HODs reach admin
        $this->actingAs($this->examiner)->get('/admin')->assertForbidden();
        $this->actingAs($this->hod)->get('/admin')->assertOk();
        // guests are sent to sign in
        auth()->logout();
        $this->get('/')->assertRedirect('/login');
        $this->get("/assessments/{$a->id}")->assertRedirect('/login');
    }

    public function test_login_and_pages_render(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back');
        $this->post('/login', ['email' => 'examiner@t.test', 'password' => 'bad'])->assertRedirect()->assertSessionHas('error');
        $this->post('/login', ['email' => 'examiner@t.test', 'password' => self::PW])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Welcome back, Eve');
        $a = $this->newAssessment();
        $this->get("/assessments/{$a->id}")->assertOk()->assertSee('Types of questions');
        $this->get('/assessments/create')->assertOk();
        $this->getJson('/notifications')->assertOk();
    }
}
