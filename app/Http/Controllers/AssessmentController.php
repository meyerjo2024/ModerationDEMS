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
            'year_level' => ['required', \Illuminate\Validation\Rule::in(config('moderation_form.levels'))],
        ], ['number.required' => 'Choose the assessment number.', 'number.in' => 'Choose T1 to T7.', 'year_level.required' => 'Choose the year level.', 'year_level.in' => 'Choose Y0 to Y4.', 'internal_moderator_id.required' => 'Choose an internal moderator.']);
        $a = $workflow->create($request->user(), $data, $this->meta($request));

        return redirect()->route('assessments.show', $a);
    }

    public function show(Request $request, Assessment $assessment)
    {
        $a = $this->visible($request, $assessment);
        $a->load(['subject.hod', 'examiner', 'internalModerator', 'externalModerator', 'attachments', 'records.reviewer', 'signatures.user', 'auditLogs.user']);

        return view('assessments.show', ['a' => $a, 'user' => $request->user()]);
    }
}
