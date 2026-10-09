<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentKind;
use App\Exceptions\WorkflowException;
use App\Models\Assessment;
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
        $data = $request->validate(Workflow::questionRules());
        $this->workflow->saveSection1($request->user(), $a, $this->rows($data['question_types']));

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
        $data = $request->validate(Workflow::questionRules());
        $this->workflow->submitPre($request->user(), $a, $this->rows($data['question_types']), $this->signature($request), $this->meta($request));

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
        if ($data['decision'] === 'REVISION_REQUESTED' && mb_strlen(trim($data['comments'] ?? '')) < 5) {
            throw new WorkflowException('Tell the examiner what needs to change.');
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
        $d = $this->source($request) + $request->validate(['commentary' => ['required', 'string', 'min:10', 'max:6000']],
            ['commentary.min' => 'Add a few words on student performance.', 'commentary.required' => 'Add a few words on student performance.']);
        $this->workflow->submitPost($request->user(), $a, $d['source'], (float) $d['total_marks'], trim($d['commentary']), $this->signature($request), $this->meta($request));

        return $this->done($a);
    }

    public function finalReview(Request $request, Assessment $assessment)
    {
        $a = $this->visible($request, $assessment);
        $d = $request->validate([
            'decision' => ['required', Rule::in(['APPROVED', 'REVISION_REQUESTED'])],
            'comments' => ['nullable', 'string', 'max:4000'],
            'consensus' => ['nullable', 'boolean'],
            'scripts_sampled' => ['required_if:decision,APPROVED', 'nullable', 'integer', 'min:0', 'max:10000'],
            'checklist' => ['required_if:decision,APPROVED', 'nullable', 'array'],
            'checklist.*.id' => ['required', 'string'],
            'checklist.*.answer' => ['required', Rule::in(['YES', 'NO', 'NA'])],
            'checklist.*.comment' => ['nullable', 'string', 'max:500'],
        ]);
        if ($d['decision'] === 'REVISION_REQUESTED' && mb_strlen(trim($d['comments'] ?? '')) < 5) {
            throw new WorkflowException('Tell the examiner what needs to change.');
        }
        $this->workflow->finalReview($request->user(), $a, $d + ['signature' => $this->signature($request)], $this->meta($request));

        return $this->done($a);
    }

    /** Normalises booleans/numbers so stored JSON (and the signed hash) is stable. */
    private function rows(array $rows): array
    {
        return array_map(fn ($r) => [
            'type' => trim($r['type']),
            'weighting' => (float) $r['weighting'] + 0,
            'heqf_level' => (int) $r['heqf_level'],
            'aligned' => filter_var($r['aligned'], FILTER_VALIDATE_BOOLEAN),
            'comment' => trim($r['comment'] ?? ''),
        ], array_values($rows));
    }
}
