<?php

namespace App\Services;

/**
 * The narrative (discussion) text of the Dean report. Written by Groq from the anonymous figures, or as a plain-language draft
 * built from the same figures without AI. The HOD can edit every paragraph before it goes into the PDF / Word file.
 */
class DeanNarrative
{
    public const SECTIONS = [
        'summary' => 'Summary',
        'compliance' => 'Moderation compliance',
        'quality' => 'Assessment quality',
        'results' => 'Student results',
        'attention' => 'Matters for attention',
        'actions' => 'Way forward',
    ];

    public function __construct(private GroqClient $groq) {}

    /** What is sent to Groq: figures and subject codes only — no student or staff names, no e-mail addresses. */
    public function payload(array $d): array
    {
        $scrub = fn (string $t) => mb_substr(preg_replace(['/[\w.+-]+@[\w-]+\.[\w.-]+/', '/\s+/'], ['[e-mail removed]', ' '], $t), 0, 400);

        return [
            'institution' => $d['meta']['institution'], 'department' => $d['meta']['department'], 'period' => $d['meta']['period'], 'qualification_filter' => $d['meta']['qualification'], 'assessment_types' => $d['meta']['kind'],
            'at_a_glance' => \Illuminate\Support\Arr::except($d['headline'], []),
            'moderation_compliance' => \Illuminate\Support\Arr::except($d['compliance'], []),
            'assessment_quality' => ['ratings_0_poor_1_adequate_2_good' => $d['quality']['areas'], 'moderator_yes_no_checks' => $d['quality']['yn'], 'rated_poor' => $d['quality']['poor']],
            'student_results' => \Illuminate\Support\Arr::only($d['results'], ['overall', 'by_qualification', 'by_level', 'low', 'drops', 'subminimum']),
            'mark_adjustments_recommended' => array_map(fn ($x) => ['assessment' => $x['name'], 'reason' => $scrub($x['why'])], $d['exceptions']['adjustments']),
            'overdue' => array_map(fn ($x) => ['assessment' => $x['name'], 'status' => $x['status']], $d['exceptions']['overdue']),
            'external_moderator_recommendations' => array_map(fn ($x) => ['assessment' => $x['name'], 'text' => $scrub($x['text'])], $d['exceptions']['external']),
            'examiners_plans_for_next_time' => array_map(fn ($x) => ['assessment' => $x['name'], 'text' => $scrub($x['text'])], array_slice($d['actions'], 0, 25)),
        ];
    }

    /** @return array<string, string> section => text */
    public function generate(array $d): array
    {
        $system = 'You write the discussion section of a quarterly assessment-moderation overview that a Head of Department at a South African university of technology sends to the Dean. '
            .'Use ONLY the figures and facts in the JSON the user supplies; never invent numbers, names, causes or events. If a figure is missing or zero, say so plainly. '
            .'Write in formal, plain, concise British English, in the third person. Each section is one to three short paragraphs of connected prose (no bullet lists, no headings, no markdown). '
            .'Refer to assessments by subject code. Be balanced: acknowledge what is working before concerns. Do not mention individual staff or students. '
            .'Reply with a JSON object with exactly these string keys: summary, compliance, quality, results, attention, actions. '
            .'summary = overall picture in 3-5 sentences; compliance = moderation timeliness and coverage (moderators/examiners); quality = what moderators found about the assessments; '
            .'results = pass rates, notable low or falling results, absentees, subminimum; attention = adjustments, overdue items and external recommendations; actions = practical recommended next steps drawn from the data and the examiners\' own plans.';
        $raw = $this->groq->chat([['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => json_encode($this->payload($d), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]]);

        return $this->parse($raw);
    }

    /** @return array<string, string> */
    public function parse(string $raw): array
    {
        $j = json_decode($raw, true);
        if (! is_array($j) && preg_match('/\{.*\}/s', $raw, $m)) {
            $j = json_decode($m[0], true);
        }
        if (! is_array($j)) {
            throw new \RuntimeException('The AI answered in an unexpected format. Please try again.');
        }
        $out = [];
        foreach (array_keys(self::SECTIONS) as $k) {
            $v = $j[$k] ?? '';
            $out[$k] = trim(is_array($v) ? implode("\n\n", array_map('strval', $v)) : (string) $v);
        }
        if (! array_filter($out)) {
            throw new \RuntimeException('The AI returned no text. Please try again.');
        }

        return $out;
    }

    /** A plain-language draft from the figures, without AI. @return array<string, string> */
    public function draft(array $d): array
    {
        $h = $d['headline'];
        $c = $d['compliance'];
        $q = $d['quality'];
        $r = $d['results'];
        $e = $d['exceptions'];
        $m = $d['meta'];
        $n = $h['assessments'];
        $s = fn (int $x, string $one, string $many) => $x.' '.($x === 1 ? $one : $many);
        $scope = ($m['period'] === 'All dates' ? 'all recorded dates' : $m['period']).($m['qualification'] === 'All qualifications' ? '' : " ({$m['qualification']})");

        $summary = $n === 0 ? "No assessments were recorded for {$scope}." :
            "This report covers {$s($h['subjects'], 'subject', 'subjects')} and {$s($n, 'assessment', 'assessments')} for {$scope}. {$s($h['completed'], 'assessment has', 'assessments have')} been fully moderated and signed off"
            .($h['completed_pct'] !== null ? " ({$h['completed_pct']}%)" : '').", {$h['pending']} await".($h['pending'] === 1 ? 's' : '')." a moderator or signatory and {$h['in_process']} ".($h['in_process'] === 1 ? 'is' : 'are').' still with the examiner. '
            .($h['overdue'] ? "{$s($h['overdue'], 'assessment is', 'assessments are')} past its moderation deadline." : 'None is past its moderation deadline.');

        $cov = [];
        if ($h['no_moderator']) {
            $cov[] = $s(count($h['no_moderator']), 'subject has', 'subjects have').' no internal moderator attached ('.implode(', ', array_map(fn ($x) => explode(' ', $x)[0], $h['no_moderator'])).')';
        }
        if ($h['no_examiner']) {
            $cov[] = $s(count($h['no_examiner']), 'subject has', 'subjects have').' no examiner on the system ('.implode(', ', array_map(fn ($x) => explode(' ', $x)[0], $h['no_examiner'])).')';
        }
        $compliance = "Pre-moderation was confirmed before the students wrote for {$c['pre_yes']} assessment".($c['pre_yes'] === 1 ? '' : 's').($c['pre_no'] ? " and not for {$c['pre_no']}" : '').($c['pre_unknown'] ? "; it is not yet recorded for {$c['pre_unknown']}" : '').'. '
            .($c['on_time'] + $c['late'] ? "Of the completed assessments, {$c['on_time']} finished within the post-moderation window and {$c['late']} finished late. " : '')
            .($c['avg_revisions'] !== null ? "{$s($c['needing_revision'], 'record needed', 'records needed')} revision after pre-moderation (an average of {$c['avg_revisions']} ".((float) $c['avg_revisions'] === 1.0 ? 'revision' : 'revisions')." per submitted record). " : '')
            .($cov ? 'Coverage needs attention: '.implode('; ', $cov).'.' : 'Every subject has both an examiner and an internal moderator.');

        $rated = array_values(array_filter($q['areas'], fn ($a) => $a['avg'] !== null));
        usort($rated, fn ($a, $b) => $b['avg'] <=> $a['avg']);
        $quality = $rated
            ? "Internal moderators rated {$q['rated']} assessment".($q['rated'] === 1 ? '' : 's').' on six areas (0 poor, 1 adequate, 2 good). The strongest area was '.mb_strtolower($rated[0]['label'])." ({$rated[0]['avg']}) and the weakest was ".mb_strtolower(end($rated)['label']).' ('.end($rated)['avg'].'). '
                .($q['poor'] ? $s(count($q['poor']), 'assessment was', 'assessments were').' rated poor in at least one area ('.implode('; ', array_map(fn ($p) => $p['name'], $q['poor'])).').' : 'No assessment was rated poor in any area.')
            : 'No moderator ratings have been recorded yet for this selection.';

        $results = $r['overall']['assessments']
            ? "Results are available for {$s($r['overall']['assessments'], 'assessment', 'assessments')} ({$r['overall']['candidates']} candidate results) with an overall pass rate of {$r['overall']['pass_rate']}%. "
                .($r['low'] ? 'Pass rates below 50% were recorded for '.implode('; ', array_map(fn ($x) => "{$x['name']} ({$x['pass_rate']}%)", $r['low'])).'. ' : 'No assessment had a pass rate below 50%. ')
                .($r['drops'] ? 'Pass rates fell by 10 points or more for '.implode('; ', array_map(fn ($x) => $x['name'], $r['drops'])).'. ' : '')
                .($r['subminimum']['assessments'] ? "{$r['subminimum']['not_met']} of {$r['subminimum']['students']} students did not meet a subminimum." : '')
            : 'No student results have been submitted yet for this selection.';

        $attention = trim(($e['adjustments'] ? $s(count($e['adjustments']), 'assessment has', 'assessments have').' a recommended general adjustment of marks ('.implode('; ', array_map(fn ($x) => $x['name'], $e['adjustments'])).'). ' : 'No general adjustment of marks was recommended. ')
            .($e['overdue'] ? $s(count($e['overdue']), 'record is', 'records are').' overdue ('.implode('; ', array_map(fn ($x) => $x['name'], $e['overdue'])).'). ' : 'Nothing is overdue. ')
            .($e['external'] ? 'External moderators made '.$s(count($e['external']), 'recommendation', 'recommendations').' for follow-up.' : ''));

        $actions = ($d['actions'] ? 'Examiners identified '.$s(count($d['actions']), 'improvement', 'improvements').' for next time, which the department will follow up. ' : '')
            .($cov ? 'Attach an internal moderator or examiner to the subjects listed above. ' : '')
            .($h['overdue'] ? 'Clear the overdue records first.' : 'Continue to moderate before the students write.');

        return compact('summary', 'compliance', 'quality', 'results', 'attention', 'actions');
    }
}
