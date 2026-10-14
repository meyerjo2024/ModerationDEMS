<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Subject;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Examiners and internal moderators choose the subjects they are responsible for. */
class MySubjectsController extends Controller
{
    private const ROLES = [Role::Examiner, Role::InternalModerator];

    public function index(Request $request)
    {
        $user = $request->user();
        $roles = array_values(array_filter(self::ROLES, fn (Role $r) => $user->hasRole($r)));
        $chosen = DB::table('subject_user')->where('user_id', $user->id)->get()->groupBy('role')->map(fn ($g) => $g->pluck('subject_id')->all());

        return view('my-subjects', [
            'roles' => $roles,
            'chosen' => $chosen,
            'plans' => \App\Models\AssessmentPlan::where('user_id', $user->id)->get()->groupBy('subject_id')->map(fn ($g) => $g->mapWithKeys(fn ($p) => [$p->number => $p->assessment_date->toDateString()])->all())->all(),
            'subjects' => Subject::orderBy('qualification')->orderBy('code')->get()->groupBy(fn ($s) => $s->qualification ?: 'Other'),
        ]);
    }

    public function update(Request $request, Audit $audit)
    {
        $user = $request->user();
        $d = $request->validate(['subjects' => ['nullable', 'array'], 'subjects.*' => ['array'], 'subjects.*.*' => ['integer', 'exists:subjects,id'],
            'plans' => ['nullable', 'array'], 'plans.*' => ['array'], 'plans.*.*' => ['nullable', 'date']]);
        DB::transaction(function () use ($user, $d) {
            foreach (self::ROLES as $role) {
                if (! $user->hasRole($role)) {
                    continue;
                }
                DB::table('subject_user')->where('user_id', $user->id)->where('role', $role->value)->delete();
                foreach (array_unique($d['subjects'][$role->value] ?? []) as $sid) {
                    DB::table('subject_user')->insert(['user_id' => $user->id, 'subject_id' => $sid, 'role' => $role->value]);
                }
            }
        });
        // the examiner's test calendar: only for subjects they are an examiner of, only the configured tests, only real dates
        if ($user->hasRole(Role::Examiner)) {
            $mine = DB::table('subject_user')->where('user_id', $user->id)->where('role', Role::Examiner->value)->pluck('subject_id')->all();
            DB::transaction(function () use ($user, $d, $mine) {
                \App\Models\AssessmentPlan::where('user_id', $user->id)->delete();
                foreach ($d['plans'] ?? [] as $sid => $tests) {
                    foreach ($tests as $number => $date) {
                        if ($date && in_array((int) $sid, $mine, true) && in_array($number, config('dems.assessment_numbers'), true)) {
                            \App\Models\AssessmentPlan::create(['user_id' => $user->id, 'subject_id' => (int) $sid, 'number' => $number, 'assessment_date' => $date]);
                        }
                    }
                }
            });
            $audit->log('ASSESSMENTS_PLANNED', null, $user->id, ['count' => \App\Models\AssessmentPlan::where('user_id', $user->id)->count()], $request->ip());
        }
        $audit->log('SUBJECTS_CHOSEN', null, $user->id, ['count' => DB::table('subject_user')->where('user_id', $user->id)->count()], $request->ip());

        return back()->with('status', 'Your subjects and assessment dates are saved.');
    }
}
