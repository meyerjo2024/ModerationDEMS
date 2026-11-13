<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Subject;
use App\Models\User;
use App\Services\Workflow;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function create(Request $request)
    {
        return view('assessments.create', [
            'defaults' => \Illuminate\Support\Facades\DB::table('subject_user')->where('user_id', $request->user()->id)->where('role', Role::Examiner->value)->whereNotNull('default_internal_moderator_id')->pluck('default_internal_moderator_id', 'subject_id')->all(),
            'plans' => \App\Models\AssessmentPlan::where('user_id', $request->user()->id)->get()->groupBy('subject_id')->map(fn ($g) => $g->mapWithKeys(fn ($p) => [$p->number => $p->assessment_date->toDateString()])->all())->all(),
            'subjects' => $request->user()->subjects()->wherePivot('role', Role::Examiner->value)->orderBy('code')->get(),
            'internals' => User::withRole(Role::InternalModerator)->where('active', true)->where('id', '!=', $request->user()->id)->with('subjects')->orderBy('name')->get(),
            'externals' => User::withRole(Role::ExternalModerator)->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Workflow $workflow)
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer'],
            'number' => ['required', 'string', \Illuminate\Validation\Rule::in(config('dems.assessment_numbers'))],
            'internal_moderator_id' => ['required', 'integer'],
            'external_moderator_id' => ['nullable', 'integer'],
            'assessment_date' => ['nullable', 'date'],
            'academic_year' => ['nullable', 'integer', 'between:2000,2100'],
            'qualification' => ['nullable', 'string', 'max:200'],
            'label' => ['nullable', 'string', 'max:80'],
            'year_level' => ['required', \Illuminate\Validation\Rule::in(config('moderation_form.levels'))],
        ], ['number.required' => 'Choose the assessment number.', 'number.in' => 'Choose a test (T1–T7) or a supplementary, special, sick or deferred assessment.', 'year_level.required' => 'Choose the year level.', 'year_level.in' => 'Choose Y0 to Y4.', 'internal_moderator_id.required' => 'Choose an internal moderator.']);
        $a = $workflow->create($request->user(), $data, $this->meta($request));

        return redirect()->route('assessments.show', $a);
    }

    /**
     * The Head of Department removes a record for good — a demo or practice run. Needs their password and the word DELETE.
     * Files, comments, signatures and the record's own audit trail go with it; one entry is kept in the general audit log.
     */
    public function destroy(Request $request, Assessment $assessment, \App\Services\FileStore $files, \App\Services\Audit $audit)
    {
        $a = $this->visible($request, $assessment);
        abort_unless($a->subject->hod_id === $request->user()->id, 403, 'Only the Head of Department of this subject can delete a record.');
        $request->validate(['current_password' => ['required', 'string'], 'confirm_text' => ['required', 'in:DELETE']], ['confirm_text.in' => 'Type the word DELETE to confirm.', 'confirm_text.required' => 'Type the word DELETE to confirm.']);
        if (! \Illuminate\Support\Facades\Hash::check($request->input('current_password'), $request->user()->password)) {
            return back()->withErrors(['current_password' => 'Your password is not correct.']);
        }
        $a->load('attachments', 'examiner', 'internalModerator', 'externalModerator');
        $title = $a->title();
        $keys = $a->attachments->pluck('storage_key')->all();
        $people = array_filter([$a->examiner_id, $a->internal_moderator_id, $a->external_moderator_id]);
        $details = ['title' => $title, 'subject' => $a->subject->code, 'status' => $a->status->value, 'examiner' => $a->examiner->name, 'internal_moderator' => $a->internalModerator?->name,
            'documents' => $a->attachments->count(), 'was_completed' => $a->status === \App\Enums\AssessmentStatus::Completed];
        \Illuminate\Support\Facades\DB::transaction(function () use ($a, $audit, $request, $details, $people, $title) {
            $a->delete();
            $audit->log('ASSESSMENT_DELETED', null, $request->user()->id, $details, $request->ip());
            foreach (array_diff(array_unique($people), [$request->user()->id]) as $id) {
                \App\Models\Notification::create(['user_id' => $id, 'message' => "{$request->user()->name} deleted the record {$title} (practice or demo data)."]);
            }
        });
        foreach ($keys as $k) {
            try {
                $files->delete($k);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('overview.subject', $a->subject_id)->with('status', "{$title} was deleted.");
    }

    public function show(Request $request, Assessment $assessment)
    {
        $a = $this->visible($request, $assessment);
        $a->load(['subject.hod', 'examiner', 'internalModerator', 'externalModerator', 'attachments', 'records.reviewer', 'signatures.user', 'auditLogs.user']);

        return view('assessments.show', ['a' => $a, 'user' => $request->user()]);
    }
}
