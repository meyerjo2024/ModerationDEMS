<?php

namespace App\Http\Controllers;

use App\Enums\AssessmentStatus as S;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\AssessmentPlan;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Head of Department: moderation progress per qualification, drill-down to subject, coverage of moderators and staff. */
class OverviewController extends Controller
{
    /** pending = waiting on a moderator or signatory; in process = with the examiner; overdue overlaps both. */
    private const PENDING = [S::PendingPreModeration, S::PendingFinalModeration, S::PendingExternalModeration, S::PendingSection3Signoff];

    public function index(Request $request)
    {
        $hod = $request->user();
        $f = [
            'qualification' => (string) $request->query('qualification', ''),
            'staff' => (int) $request->query('staff', 0),
            'staff_role' => in_array($request->query('staff_role'), ['EXAMINER', 'INTERNAL_MODERATOR'], true) ? $request->query('staff_role') : '',
            'coverage' => in_array($request->query('coverage'), ['no_moderator', 'no_examiner', 'covered'], true) ? $request->query('coverage') : '',
            'from' => $this->date($request->query('from')),
            'to' => $this->date($request->query('to')),
            'q' => trim((string) $request->query('q', '')),
        ];

        $subjects = $this->scopeSubjects($hod);
        $myQuals = $hod->hasRole(Role::Hod) ? null : array_map('mb_strtolower', $hod->programme_qualifications ?? []);
        $links = DB::table('subject_user')->whereIn('subject_id', $subjects->pluck('id'))->get()->groupBy('subject_id');
        $people = User::whereIn('id', $links->flatten()->pluck('user_id')->unique())->pluck('name', 'id');
        $assessments = Assessment::with('subject')->whereIn('subject_id', $subjects->pluck('id'))->get()->groupBy('subject_id');

        $rows = $subjects->map(function (Subject $s) use ($links, $people, $assessments) {
            $l = $links->get($s->id, collect());
            $names = fn (string $role) => $l->where('role', $role)->pluck('user_id')->map(fn ($id) => ['id' => $id, 'name' => $people[$id] ?? '?'])->values();
            $a = $assessments->get($s->id, collect());
            $overdue = $a->filter(fn ($x) => ($x->deadline()['state'] ?? '') === 'overdue');

            return (object) [
                'subject' => $s,
                'quals' => array_values(array_filter(array_map('trim', explode(', ', (string) $s->qualification)))) ?: ['(No qualification)'],
                'examiners' => $names('EXAMINER'), 'moderators' => $names('INTERNAL_MODERATOR'),
                'total' => $a->count(),
                'pending' => $a->filter(fn ($x) => in_array($x->status, self::PENDING, true))->count(),
                'process' => $a->filter(fn ($x) => in_array($x->status, [S::Draft, S::RevisionRequested, S::ReadyForPostModeration], true))->count(),
                'done' => $a->filter(fn ($x) => $x->status === S::Completed)->count(),
                'overdue' => $overdue->count(),
                'next' => $a->filter(fn ($x) => $x->status !== S::Completed && $x->assessment_date)->min(fn ($x) => $x->assessment_date),
            ];
        });

        // qualification summary (a subject offered in several qualifications counts in each)
        $quals = [];
        foreach ($rows as $r) {
            foreach ($r->quals as $q) {
                $x = $quals[$q] ??= ['name' => $q, 'subjects' => 0, 'with_mod' => 0, 'no_mod' => 0, 'no_exam' => 0, 'pending' => 0, 'process' => 0, 'done' => 0, 'overdue' => 0];
                $x['subjects']++;
                $x[$r->moderators->isEmpty() ? 'no_mod' : 'with_mod']++;
                $x['no_exam'] += $r->examiners->isEmpty() ? 1 : 0;
                foreach (['pending', 'process', 'done', 'overdue'] as $k) {
                    $x[$k] += $r->$k;
                }
                $quals[$q] = $x;
            }
        }
        if ($myQuals !== null) { // a Head of Programme sees only the qualification(s) they head, even for subjects shared with others
            $quals = array_filter($quals, fn ($q) => in_array(mb_strtolower($q['name']), $myQuals, true));
        }
        ksort($quals);

        $shown = $rows->filter(function ($r) use ($f, $myQuals) {
            if ($f['qualification'] !== '' && ! in_array($f['qualification'], $r->quals, true)) {
                return false;
            }
            if ($myQuals !== null && ! array_intersect(array_map('mb_strtolower', $r->quals), $myQuals)) {
                return false;
            }
            if ($f['staff']) {
                $pool = $f['staff_role'] === 'EXAMINER' ? $r->examiners : ($f['staff_role'] === 'INTERNAL_MODERATOR' ? $r->moderators : $r->examiners->concat($r->moderators));
                if (! $pool->contains('id', $f['staff'])) {
                    return false;
                }
            }
            if ($f['coverage'] === 'no_moderator' && $r->moderators->isNotEmpty()) {
                return false;
            }
            if ($f['coverage'] === 'no_examiner' && $r->examiners->isNotEmpty()) {
                return false;
            }
            if ($f['coverage'] === 'covered' && ($r->moderators->isEmpty() || $r->examiners->isEmpty())) {
                return false;
            }
            if ($f['q'] !== '' && ! str_contains(mb_strtolower($r->subject->code.' '.$r->subject->name), mb_strtolower($f['q']))) {
                return false;
            }

            return true;
        })->values();

        // tests written in the chosen window (default: the next 30 days): started assessments plus planned tests not yet started
        $from = $f['from'] ?? now('UTC')->toDateString();
        $to = $f['to'] ?? now('UTC')->addDays(30)->toDateString();
        $ids = $shown->pluck('subject.id');
        $tests = collect();
        foreach ($assessments->flatten() as $a) {
            if ($ids->contains($a->subject_id) && $a->assessment_date && $a->assessment_date->toDateString() >= $from && $a->assessment_date->toDateString() <= $to) {
                $tests->push((object) ['date' => $a->assessment_date, 'subject' => $a->subject, 'label' => $a->number_label, 'status' => $a->status->label(), 'url' => $a->subject->hod_id === $hod->id ? route('assessments.show', $a) : null, 'who' => $a->examiner?->name]);
            }
        }
        AssessmentPlan::with(['subject', 'user'])->whereIn('subject_id', $ids)->whereDate('assessment_date', '>=', $from)->whereDate('assessment_date', '<=', $to)->get()
            ->reject(fn ($p) => $p->started())
            ->each(fn ($p) => $tests->push((object) ['date' => $p->assessment_date, 'subject' => $p->subject, 'label' => $p->number_label, 'status' => 'Planned — not started', 'url' => route('overview.subject', $p->subject), 'who' => $p->user?->name]));

        return view('overview.index', [
            'f' => $f, 'quals' => $quals, 'rows' => $shown, 'tests' => $tests->sortBy('date')->values(), 'from' => $from, 'to' => $to,
            'staff' => User::where('active', true)->where(fn ($q) => $q->withRole(Role::Examiner)->orWhere(fn ($w) => $w->withRole(Role::InternalModerator)))->orderBy('name')->get(['id', 'name']),
            'totals' => ['subjects' => $rows->count(), 'no_mod' => $rows->filter(fn ($r) => $r->moderators->isEmpty())->count(), 'no_exam' => $rows->filter(fn ($r) => $r->examiners->isEmpty())->count()],
        ]);
    }

    public function subject(Request $request, Subject $subject)
    {
        abort_unless($this->scopeSubjects($request->user())->contains('id', $subject->id), 404);
        $l = DB::table('subject_user')->where('subject_id', $subject->id)->get();
        $people = User::whereIn('id', $l->pluck('user_id')->merge($l->pluck('default_internal_moderator_id'))->filter()->unique())->pluck('name', 'id');

        return view('overview.subject', [
            'subject' => $subject, 'canOpen' => $subject->hod_id === $request->user()->id,
            'assessments' => Assessment::with(['examiner', 'internalModerator'])->where('subject_id', $subject->id)->orderByDesc('assessment_date')->get(),
            'plans' => AssessmentPlan::with('user')->where('subject_id', $subject->id)->orderBy('assessment_date')->get()->reject(fn ($p) => $p->started()),
            'examiners' => $l->where('role', 'EXAMINER')->map(fn ($x) => ['name' => $people[$x->user_id] ?? '?', 'default' => $x->default_internal_moderator_id ? ($people[$x->default_internal_moderator_id] ?? null) : null]),
            'moderators' => $l->where('role', 'INTERNAL_MODERATOR')->map(fn ($x) => $people[$x->user_id] ?? '?'),
        ]);
    }

    /** A Head of Department sees their own subjects; a Head of Programme sees every subject in the qualification(s) they head. */
    private function scopeSubjects(User $u)
    {
        $quals = array_map('mb_strtolower', $u->hasRole(Role::HeadOfProgramme) ? ($u->programme_qualifications ?? []) : []);

        return Subject::orderBy('code')->get()->filter(fn (Subject $s) => ($u->hasRole(Role::Hod) && $s->hod_id === $u->id)
            || ($quals && array_intersect(array_map('mb_strtolower', $s->qualificationList()), $quals)))->values();
    }

    private function date(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
