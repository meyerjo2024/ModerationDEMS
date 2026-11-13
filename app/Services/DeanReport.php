<?php

namespace App\Services;

use App\Enums\AssessmentStatus as S;
use App\Enums\ModerationStage;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The Head of Department's overview report for the Dean: every subject, all assessments, no student-level data.
 * Separate from the per-assessment moderation report (Appendix 2).
 */
class DeanReport
{
    private const PENDING = [S::PendingPreModeration, S::PendingFinalModeration, S::PendingExternalModeration, S::PendingSection3Signoff];

    /** @param array{year?: ?int, qualification?: string, kind?: string, from?: ?string, to?: ?string, to_whom?: string, comment?: string} $f */
    public function build(User $hod, array $f): array
    {
        $kind = in_array($f['kind'] ?? 'all', ['tests', 'additional'], true) ? $f['kind'] : 'all';
        $qual = trim((string) ($f['qualification'] ?? ''));
        $year = ! empty($f['year']) ? (int) $f['year'] : null;
        $from = $f['from'] ?? null;
        $to = $f['to'] ?? null;

        $subjects = Subject::where('hod_id', $hod->id)->orderBy('code')->get()
            ->filter(fn (Subject $s) => $qual === '' || in_array(mb_strtolower($qual), array_map('mb_strtolower', $s->qualificationList()), true))->values();
        $links = DB::table('subject_user')->whereIn('subject_id', $subjects->pluck('id'))->get()->groupBy('subject_id');

        $assessments = Assessment::with(['subject', 'examiner:id,name', 'internalModerator:id,name', 'records'])->whereIn('subject_id', $subjects->pluck('id'))
            ->when($year, fn ($q) => $q->where('academic_year', $year))
            ->when($from, fn ($q) => $q->whereDate('assessment_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('assessment_date', '<=', $to))
            ->get()
            ->filter(fn (Assessment $a) => $kind === 'all' || ($kind === 'tests') === (bool) preg_match('/^T\d+$/', $a->number))
            ->sortBy([['subject.code', 'asc'], ['assessment_date', 'asc'], ['created_at', 'asc']])->values();

        $rows = $assessments->map(fn (Assessment $a) => $this->row($a))->values();

        return [
            'meta' => [
                'institution' => config('dems.institution'), 'department' => $hod->department, 'hod' => $hod->name, 'generated' => now('UTC')->format('j F Y, H:i').' UTC',
                'to_whom' => trim((string) ($f['to_whom'] ?? '')) ?: 'The Dean', 'comment' => trim((string) ($f['comment'] ?? '')),
                'period' => $this->period($year, $from, $to), 'qualification' => $qual ?: 'All qualifications', 'kind' => ['all' => 'All assessments', 'tests' => 'Tests (T1–T7)', 'additional' => 'Supplementary, special, sick and deferred'][$kind],
            ],
            'headline' => $this->headline($subjects, $links, $rows),
            'compliance' => $this->compliance($rows),
            'quality' => $this->quality($rows),
            'results' => $this->results($rows, $subjects),
            'exceptions' => $this->exceptions($rows),
            'actions' => $this->actions($rows),
            'appendix' => $rows->map(fn ($r) => \Illuminate\Support\Arr::only($r, ['code', 'subject', 'title', 'qualification', 'date', 'status', 'examiner', 'moderator', 'overdue', 'revisions', 'candidates', 'pass_rate', 'average', 'adjust', 'sub_pct']))->all(),
        ];
    }

    private function period(?int $year, ?string $from, ?string $to): string
    {
        $bits = [];
        if ($year) {
            $bits[] = "Academic year {$year}";
        }
        if ($from || $to) {
            $bits[] = trim(($from ? \Carbon\Carbon::parse($from)->format('j M Y') : 'start').' – '.($to ? \Carbon\Carbon::parse($to)->format('j M Y') : 'today'));
        }

        return $bits ? implode(' · ', $bits) : 'All dates';
    }

    private function row(Assessment $a): array
    {
        $s1m = $a->s1_moderator ?? [];
        $s2m = $a->s2_moderator ?? [];
        $s2e = $a->s2_examiner ?? [];
        $s3 = $a->s3_external ?? [];
        $bucket = $a->status === S::Completed ? 'done' : (in_array($a->status, self::PENDING, true) ? 'pending' : 'process');
        $dl = $a->deadline();
        $preOk = $a->records->first(fn ($r) => $r->stage === ModerationStage::PreModeration && $r->decision === 'APPROVED');
        $date = $a->assessment_date;
        $quals = $a->subject->qualificationList();
        $res = $a->resultsSummary();
        $registered = $s2e['registered'] ?? $a->enrolled_count ?? $a->candidate_count;

        return [
            'id' => $a->id, 'code' => $a->subject->code, 'subject' => $a->subject->name, 'title' => $a->number_label.($a->label !== '' ? " ({$a->label})" : ''),
            'qualification' => $a->qualification ?: ($quals[0] ?? '(none)'), 'year_level' => $a->year_level, 'date' => $date?->format('j M Y'), 'date_raw' => $date?->toDateString(),
            'examiner' => $a->examiner?->name, 'moderator' => $a->internalModerator?->name, 'status' => $a->status->label(), 'bucket' => $bucket, 'overdue' => ($dl['state'] ?? '') === 'overdue',
            'revisions' => max(0, (int) $a->revision - 1),
            'pre_before' => ($s1m['questions']['q1']['answer'] ?? null), // moderated before the students completed the assessment
            'pre_done_on' => $preOk?->created_at?->format('j M Y'),
            'on_time' => $a->status === S::Completed && $a->completed_at && $a->postDue() ? $a->completed_at->lte($a->postDue()->endOfDay()) : null,
            'ratings' => $s1m['ratings'] ?? [], 'yn' => collect($s1m['questions'] ?? [])->map(fn ($q) => $q['answer'] ?? null)->all(),
            'candidates' => $a->candidate_count, 'registered' => $registered, 'pass_rate' => $a->pass_rate, 'pass_count' => $a->pass_count, 'average' => $a->class_average,
            'highest' => $a->highest_mark, 'lowest' => $a->lowest_mark,
            'sub_pct' => $res['subminimum'] ?? null, 'sub_met' => $res['met'] ?? null, 'sub_not_met' => $res['not_met'] ?? null, 'sub_pass_below' => $res['passed_but_below'] ?? null,
            'adjust' => ($s2m['adjustments']['recommended'] ?? null) === 'YES' || ($s3['adjustments']['recommended'] ?? null) === 'YES',
            'adjust_why' => trim(implode(' ', array_filter([$s2m['adjustments']['specify'] ?? '', $s3['adjustments']['specify'] ?? '']))),
            'next_time' => trim((string) ($s2e['answers']['q5'] ?? '')), 'external_rec' => trim((string) ($s3['items']['recommendations'] ?? '')),
            'sort_key' => $a->subject->code.'|'.($a->assessment_date?->toDateString() ?? '').'|'.$a->number, 'subject_id' => $a->subject_id, 'academic_year' => $a->academic_year,
        ];
    }

    private function headline($subjects, $links, $rows): array
    {
        $noMod = $subjects->filter(fn ($s) => $links->get($s->id, collect())->where('role', Role::InternalModerator->value)->isEmpty());
        $noEx = $subjects->filter(fn ($s) => $links->get($s->id, collect())->where('role', Role::Examiner->value)->isEmpty());
        $n = $rows->count();
        $done = $rows->where('bucket', 'done')->count();

        return [
            'subjects' => $subjects->count(), 'assessments' => $n, 'completed' => $done, 'pending' => $rows->where('bucket', 'pending')->count(), 'in_process' => $rows->where('bucket', 'process')->count(),
            'overdue' => $rows->where('overdue', true)->count(), 'completed_pct' => $n ? round($done / $n * 100) : null,
            'no_moderator' => $noMod->map(fn ($s) => $s->code.' '.$s->name)->values()->all(), 'no_examiner' => $noEx->map(fn ($s) => $s->code.' '.$s->name)->values()->all(),
            'no_activity' => $subjects->filter(fn ($s) => ! $rows->contains('subject_id', $s->id))->count(),
        ];
    }

    private function compliance($rows): array
    {
        $known = $rows->filter(fn ($r) => $r['pre_before'] !== null);
        $completed = $rows->where('bucket', 'done')->filter(fn ($r) => $r['on_time'] !== null);
        $submitted = $rows->filter(fn ($r) => $r['status'] !== 'Draft');

        return [
            'pre_yes' => $known->where('pre_before', 'YES')->count(), 'pre_no' => $known->where('pre_before', 'NO')->count(), 'pre_unknown' => $rows->count() - $known->count(),
            'on_time' => $completed->where('on_time', true)->count(), 'late' => $completed->where('on_time', false)->count(),
            'needing_revision' => $submitted->where('revisions', '>', 0)->count(), 'avg_revisions' => $submitted->count() ? round($submitted->avg('revisions'), 1) : null,
            'late_list' => $completed->where('on_time', false)->map(fn ($r) => $r['code'].' '.$r['title'])->values()->all(),
        ];
    }

    private function quality($rows): array
    {
        $labels = config('moderation_form.ratings');
        $areas = [];
        foreach ($labels as $k => $label) {
            $vals = $rows->map(fn ($r) => $r['ratings'][$k] ?? null)->filter(fn ($v) => $v !== null && $v !== '');
            $areas[] = ['label' => $label, 'n' => $vals->count(), 'avg' => $vals->count() ? round($vals->avg(), 2) : null, 'poor' => $vals->filter(fn ($v) => (int) $v === 0)->count()];
        }
        $poor = $rows->filter(fn ($r) => collect($r['ratings'])->contains(fn ($v) => $v !== null && (int) $v === 0))
            ->map(fn ($r) => ['name' => $r['code'].' '.$r['title'], 'areas' => collect($r['ratings'])->filter(fn ($v) => (int) $v === 0)->keys()->map(fn ($k) => $labels[$k] ?? $k)->values()->all()])->values()->all();
        $yn = [];
        foreach (config('moderation_form.s1_questions') as $k => $text) {
            $vals = $rows->map(fn ($r) => $r['yn'][$k] ?? null)->filter();
            $yn[] = ['label' => $text, 'yes' => $vals->filter(fn ($v) => $v === 'YES')->count(), 'no' => $vals->filter(fn ($v) => $v === 'NO')->count()];
        }

        return ['areas' => $areas, 'poor' => $poor, 'yn' => $yn, 'rated' => $rows->filter(fn ($r) => $r['ratings'])->count()];
    }

    private function results($rows, $subjects): array
    {
        $with = $rows->filter(fn ($r) => $r['candidates'] !== null && $r['pass_rate'] !== null);
        $group = function ($key) use ($with) {
            return $with->groupBy(fn ($r) => $r[$key] ?: '(not set)')->map(function ($g, $name) {
                $cand = $g->sum('candidates');

                return ['name' => $name, 'assessments' => $g->count(), 'candidates' => $cand, 'pass_rate' => $cand ? round($g->sum('pass_count') / $cand * 100, 1) : null,
                    'average' => round($g->avg('average'), 1), 'absent' => $g->sum(fn ($r) => max(0, ($r['registered'] ?? $r['candidates']) - $r['candidates']))];
            })->sortKeys()->values()->all();
        };
        $drops = [];
        foreach ($with->groupBy(fn ($r) => $r['subject_id'].'|'.$r['academic_year']) as $g) {
            $g = $g->sortBy('sort_key')->values();
            for ($i = 1; $i < $g->count(); $i++) {
                $d = round((float) $g[$i]['pass_rate'] - (float) $g[$i - 1]['pass_rate'], 1);
                if ($d <= -10) {
                    $drops[] = ['name' => $g[$i]['code'].' '.$g[$i]['subject'], 'text' => "{$g[$i - 1]['title']} {$g[$i - 1]['pass_rate']}% → {$g[$i]['title']} {$g[$i]['pass_rate']}% ({$d} points)"];
                }
            }
        }
        $sub = $with->filter(fn ($r) => $r['sub_pct'] !== null);

        return [
            'by_qualification' => $group('qualification'), 'by_level' => $group('year_level'),
            'overall' => ['assessments' => $with->count(), 'candidates' => $with->sum('candidates'), 'pass_rate' => $with->sum('candidates') ? round($with->sum('pass_count') / $with->sum('candidates') * 100, 1) : null],
            'low' => $with->filter(fn ($r) => (float) $r['pass_rate'] < 50)->sortBy('pass_rate')->map(fn ($r) => ['name' => $r['code'].' '.$r['subject'].' · '.$r['title'], 'pass_rate' => $r['pass_rate'], 'candidates' => $r['candidates']])->values()->all(),
            'drops' => $drops,
            'subminimum' => ['assessments' => $sub->count(), 'students' => $sub->sum('candidates'), 'not_met' => $sub->sum('sub_not_met'), 'passed_below' => $sub->sum('sub_pass_below')],
        ];
    }

    private function exceptions($rows): array
    {
        return [
            'adjustments' => $rows->where('adjust', true)->map(fn ($r) => ['name' => $r['code'].' · '.$r['title'], 'why' => $r['adjust_why']])->values()->all(),
            'overdue' => $rows->where('overdue', true)->map(fn ($r) => ['name' => $r['code'].' · '.$r['title'], 'status' => $r['status'], 'examiner' => $r['examiner']])->values()->all(),
            'external' => $rows->filter(fn ($r) => $r['external_rec'] !== '')->map(fn ($r) => ['name' => $r['code'].' · '.$r['title'], 'text' => $r['external_rec']])->values()->all(),
        ];
    }

    private function actions($rows): array
    {
        return $rows->filter(fn ($r) => $r['next_time'] !== '')->map(fn ($r) => ['name' => $r['code'].' '.$r['subject'].' · '.$r['title'], 'text' => $r['next_time']])->values()->all();
    }
}
