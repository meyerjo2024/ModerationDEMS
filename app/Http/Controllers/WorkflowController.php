<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentStatus;
use App\Enums\AttachmentKind;
use App\Exceptions\WorkflowException;
use App\Models\Assessment;
use App\Services\ModerationForm;
use App\Services\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** JSON endpoints behind the workflow screens (all authorisation lives in App\Services\Workflow). */
class WorkflowController extends Controller
{
    public function __construct(private Workflow $workflow) {}

    private function done(Assessment $a, array $extra = []): JsonResponse
    {
        return response()->json($extra + ['redirect' => route('assessments.show', $a)]);
    }

    public function saveSection1(Request $request, Assessment $assessment)
    {
        $a = $this->visible($request, $assessment);
        $data = $request->validate(ModerationForm::rules('s1_examiner', 's1', draft: true));
        $this->workflow->saveSection1($request->user(), $a, $data['s1'] ?? []);

        return response()->json(['ok' => true]);
    }

    public function upload(Request $request, Assessment $assessment)
    {
        $a = $this->visible($request, $assessment);
        $request->validate([
            'kind' => ['required', Rule::in(['PAPER', 'MEMO', 'MARKS', 'SAMPLE_SCRIPT'])],
            'file' => ['required', 'file'],
        ], ['file.required' => 'Attach a file and choose what it is.']);
        $file = $request->file('file');
        $att = $this->workflow->upload($request->user(), $a, AttachmentKind::from($request->input('kind')), $file->getClientOriginalName(), file_get_contents($file->getRealPath()), $this->meta($request));

        return response()->json(['attachment' => ['id' => $att->id, 'kind' => $att->kind->value, 'filename' => $att->filename]]);
    }

    public function submitPre(Request $request, Assessment $assessment)
    {
        $a = $this->visible($request, $assessment);
        $data = $request->validate(ModerationForm::rules('s1_examiner', 's1'));
        $this->workflow->submitPre($request->user(), $a, $data['s1'], $this->signature($request), $this->meta($request));

        return $this->done($a);
    }

    public function preReview(Request $request, Assessment $assessment)
    {
        $a = $this->visible($request, $assessment);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['APPROVED', 'REVISION_REQUESTED'])],
            'comments' => ['nullable', 'string', 'max:4000'],
            'consensus' => ['nullable', 'boolean'],
        ]);
        if ($data['decision'] === 'REVISION_REQUESTED') {
            $this->needFeedback($data);
        } else {
            $data += $request->validate(ModerationForm::rules('s1_moderator', 's1_moderator'));
        }
        $this->workflow->preReview($request->user(), $a, $data + ['signature' => $this->signature($request)], $this->meta($request));

        return $this->done($a);
    }

    public function marks(Request $request, Assessment $assessment)
    {
        $a = $this->visible($request, $assessment);
        $request->validate(['attachment' => ['required', 'string']]);

        return response()->json(['sheets' => $this->workflow->marksColumns($request->user(), $a, $request->query('attachment'))]);
    }

    private function source(Request $request): array
    {
        return $request->validate([
            'source.type' => ['required', Rule::in(['excel', 'manual'])],
            'source.attachment_id' => ['required_if:source.type,excel', 'nullable', 'string'],
            'source.sheet' => ['required_if:source.type,excel', 'nullable', 'string'],
            'source.column' => ['required_if:source.type,excel', 'nullable', 'integer', 'min:1', 'max:500'],
            'source.text' => ['required_if:source.type,manual', 'nullable', 'string', 'max:200000'],
            'total_marks' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ]);
    }

    public function calculate(Request $request, Assessment $assessment)
    {
        $a = $this->visible($request, $assessment);
        $d = $this->source($request);

        return response()->json($this->workflow->preview($request->user(), $a, $d['source'], (float) $d['total_marks']));
    }

    public function submitPost(Request $request, Assessment $assessment)
    {
        $a = $this->visible($request, $assessment);
        $d = $this->source($request) + $request->validate(ModerationForm::rules('s2_examiner', 's2'));
        $this->workflow->submitPost($request->user(), $a, $d['source'], (float) $d['total_marks'], $d['s2'], $this->signature($request), $this->meta($request));

        return $this->done($a);
    }

    /** Gate 2 (internal moderator → Section 2 questions 6–8) or Gate 3 (external moderator → Section 3). */
    public function finalReview(Request $request, Assessment $assessment)
    {
        $a = $this->visible($request, $assessment);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['APPROVED', 'REVISION_REQUESTED'])],
            'comments' => ['nullable', 'string', 'max:4000'],
            'consensus' => ['nullable', 'boolean'],
        ]);
        if ($data['decision'] === 'REVISION_REQUESTED') {
            $this->needFeedback($data);
        } elseif ($a->status === AssessmentStatus::PendingExternalModeration) {
            $data += $request->validate(ModerationForm::rules('s3_external', 's3_external'));
        } else {
            $data += $request->validate(ModerationForm::rules('s2_moderator', 's2_moderator'));
        }
        $this->workflow->finalReview($request->user(), $a, $data + ['signature' => $this->signature($request)], $this->meta($request));

        return $this->done($a);
    }

    /** Section 3 signature of the examiner or the Head of Department. */
    public function section3Sign(Request $request, Assessment $assessment)
    {
        $a = $this->visible($request, $assessment);
        $status = $this->workflow->section3Sign($request->user(), $a, $this->signature($request), $this->meta($request));

        return $this->done($a, ['status' => $status->value]);
    }

    private function needFeedback(array $data): void
    {
        if (mb_strlen(trim($data['comments'] ?? '')) < 5) {
            throw new WorkflowException('Tell the examiner what needs to change.');
        }
    }
}
