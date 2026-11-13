@php $N = $d['narrative'] ?? []; $para = fn ($k) => ! empty($N[$k]) ? collect(preg_split('/\R+/', $N[$k]))->filter()->map(fn ($p) => '<p class="nar">'.e($p).'</p>')->implode('') : ''; $m = $d['meta']; $h = $d['headline']; $c = $d['compliance']; $q = $d['quality']; $r = $d['results']; $e = $d['exceptions']; @endphp
<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
  @page { margin: 38px 38px 50px 38px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1d1d1f; line-height: 1.4; }
  h1 { font-size: 19px; margin: 6px 0 2px; } h2 { font-size: 12px; color: #006699; margin: 16px 0 5px; border-bottom: 1px solid #006699; padding-bottom: 2px; } h3 { font-size: 9.5px; margin: 9px 0 3px; }
  .muted { color: #6e6e73; } table { width: 100%; border-collapse: collapse; } th { text-align: left; font-size: 8px; color: #6e6e73; border-bottom: 1px solid #999; padding: 3px 4px; } td { padding: 3px 4px; border-bottom: 0.5px solid #ddd; vertical-align: top; }
  .r { text-align: right; } .kpi td { border: 0.6px solid #c7c7cc; text-align: center; padding: 7px 4px; width: 16.6%; } .kpi b { font-size: 15px; display: block; }
  .bar { background: #e8e8ed; height: 6px; width: 100%; } .bar i { display: block; background: #0099cc; height: 6px; }
  .bad { color: #b4232c; } .good { color: #1f7a45; } ul { margin: 2px 0 4px 14px; padding: 0; } li { margin-bottom: 1.5px; }
  .nar { margin: 3px 0 5px; font-size: 9.5px; line-height: 1.5; text-align: justify; }
  .note { background: #f2f2f4; padding: 6px 8px; margin: 8px 0; } .sig { margin-top: 30px; }
  .foot { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 7px; color: #6e6e73; text-align: center; }
</style></head><body>
<div class="foot">{{ $m['institution'] }} · Assessment moderation overview · {{ $m['generated'] }} · no student-level data</div>
@if ($logo)<img src="{{ $logo }}" style="height:42px" alt="">@endif
<div class="muted">{{ $m['institution'] }}@if ($m['department']) · {{ $m['department'] }}@endif</div>
<h1>Assessment moderation overview</h1>
<div>Report for: <b>{{ $m['to_whom'] }}</b></div>
<div class="muted">{{ $m['period'] }} · {{ $m['qualification'] }} · {{ $m['kind'] }}<br>Prepared by {{ $m['hod'] }} (Head of Department) · {{ $m['generated'] }}</div>
@if ($m['comment'])<div class="note"><b>Head of Department’s comment:</b> {!! nl2br(e($m['comment'])) !!}</div>@endif

{!! $para('summary') !!}
<h2>1. At a glance</h2>
<table class="kpi"><tr>
  <td>Subjects<b>{{ $h['subjects'] }}</b></td><td>Assessments<b>{{ $h['assessments'] }}</b></td><td>Completed<b class="good">{{ $h['completed'] }}</b>{{ $h['completed_pct'] !== null ? $h['completed_pct'].'%' : '' }}</td>
  <td>Pending moderation<b>{{ $h['pending'] }}</b></td><td>In process<b>{{ $h['in_process'] }}</b></td><td>Overdue<b class="{{ $h['overdue'] ? 'bad' : '' }}">{{ $h['overdue'] }}</b></td></tr></table>
<ul>
  <li>{{ $h['no_moderator'] ? 'Without an internal moderator ('.count($h['no_moderator']).'): '.implode('; ', $h['no_moderator']) : 'Every subject has an internal moderator.' }}</li>
  <li>{{ $h['no_examiner'] ? 'Without an examiner ('.count($h['no_examiner']).'): '.implode('; ', $h['no_examiner']) : 'Every subject has an examiner.' }}</li>
  @if ($h['no_activity'])<li>{{ $h['no_activity'] }} subject(s) have no assessment in this period.</li>@endif</ul>

<h2>2. Moderation compliance</h2>{!! $para('compliance') !!}
<table><tr><td>Moderated before the students wrote — Yes / No / not yet recorded</td><td class="r"><b>{{ $c['pre_yes'] }} / {{ $c['pre_no'] }} / {{ $c['pre_unknown'] }}</b></td></tr>
  <tr><td>Completed within the post-moderation window / late</td><td class="r"><b>{{ $c['on_time'] }} / {{ $c['late'] }}</b></td></tr>
  <tr><td>Records needing revision after pre-moderation</td><td class="r"><b>{{ $c['needing_revision'] }}</b></td></tr>
  <tr><td>Average revisions per submitted record</td><td class="r"><b>{{ $c['avg_revisions'] ?? '—' }}</b></td></tr></table>
@if ($c['late_list'])<div class="muted">Late: {{ implode('; ', $c['late_list']) }}</div>@endif

<h2>3. Assessment quality <span class="muted" style="font-weight:normal">(internal moderators’ ratings: 0 poor · 1 adequate · 2 good)</span></h2>{!! $para('quality') !!}
<table>@foreach ($q['areas'] as $a)<tr><td style="width:42%">{{ $a['label'] }}</td><td style="width:34%"><div class="bar"><i style="width: {{ $a['avg'] !== null ? round($a['avg'] / 2 * 100) : 0 }}%"></i></div></td><td class="r">{{ $a['avg'] ?? '—' }} <span class="muted">({{ $a['n'] }}{{ $a['poor'] ? ', '.$a['poor'].' poor' : '' }})</span></td></tr>@endforeach</table>
<ul>@foreach ($q['yn'] as $a)<li>{{ $a['label'] }} — <b>{{ $a['yes'] }} yes</b>, {{ $a['no'] }} no</li>@endforeach
  @foreach ($q['poor'] as $p)<li class="bad">Rated poor: {{ $p['name'] }} ({{ implode(', ', $p['areas']) }})</li>@endforeach</ul>

<h2>4. Student results <span class="muted" style="font-weight:normal">(anonymous)</span></h2>{!! $para('results') !!}
<div>Overall: {{ $r['overall']['assessments'] }} assessment(s) with results, {{ $r['overall']['candidates'] }} candidate results{{ $r['overall']['pass_rate'] !== null ? ', pass rate '.$r['overall']['pass_rate'].'%' : '' }}.</div>
@foreach ([['Qualification', $r['by_qualification']], ['Year level', $r['by_level']]] as [$title, $set])
  @if ($set)<table style="margin-top:5px"><tr><th>{{ $title }}</th><th class="r">Assessments</th><th class="r">Candidates</th><th class="r">Pass rate</th><th class="r">Class average</th><th class="r">Absent</th></tr>
    @foreach ($set as $x)<tr><td>{{ $x['name'] }}</td><td class="r">{{ $x['assessments'] }}</td><td class="r">{{ $x['candidates'] }}</td><td class="r"><b>{{ $x['pass_rate'] !== null ? $x['pass_rate'].'%' : '—' }}</b></td><td class="r">{{ $x['average'] }}%</td><td class="r">{{ $x['absent'] }}</td></tr>@endforeach</table>@endif
@endforeach
@if ($r['low'])<h3>Pass rate below 50%</h3><ul>@foreach ($r['low'] as $x)<li class="bad">{{ $x['name'] }} — {{ $x['pass_rate'] }}% ({{ $x['candidates'] }} candidates)</li>@endforeach</ul>@endif
@if ($r['drops'])<h3>Pass rate fell by 10 points or more since the previous assessment</h3><ul>@foreach ($r['drops'] as $x)<li>{{ $x['name'] }}: {{ $x['text'] }}</li>@endforeach</ul>@endif
@if ($r['subminimum']['assessments'])<div style="margin-top:5px"><b>Subminimum:</b> {{ $r['subminimum']['assessments'] }} assessment(s) have one; {{ $r['subminimum']['not_met'] }} of {{ $r['subminimum']['students'] }} students did not meet it ({{ $r['subminimum']['passed_below'] }} of them had reached the pass mark).</div>@endif

<h2>5. Matters for attention</h2>{!! $para('attention') !!}
<h3>General mark adjustments recommended ({{ count($e['adjustments']) }})</h3><ul>@forelse ($e['adjustments'] as $x)<li>{{ $x['name'] }}@if ($x['why']): {{ $x['why'] }}@endif</li>@empty<li class="muted">None.</li>@endforelse</ul>
<h3>Overdue ({{ count($e['overdue']) }})</h3><ul>@forelse ($e['overdue'] as $x)<li>{{ $x['name'] }} — {{ $x['status'] }} (examiner: {{ $x['examiner'] }})</li>@empty<li class="muted">None.</li>@endforelse</ul>
<h3>External moderators’ recommendations ({{ count($e['external']) }})</h3><ul>@forelse ($e['external'] as $x)<li>{{ $x['name'] }}: {{ $x['text'] }}</li>@empty<li class="muted">None recorded.</li>@endforelse</ul>

<h2>6. What examiners will do differently next time</h2>{!! $para('actions') !!}
<ul>@forelse ($d['actions'] as $x)<li><b>{{ $x['name'] }}:</b> {{ $x['text'] }}</li>@empty<li class="muted">None recorded.</li>@endforelse</ul>

<div style="page-break-before: always"></div>
<h2>Appendix: all assessments ({{ count($d['appendix']) }})</h2>
<table style="font-size:7.5px"><tr><th>Subject</th><th>Assessment</th><th>Date</th><th>Status</th><th>Examiner</th><th>Moderator</th><th class="r">Cand.</th><th class="r">Pass %</th><th class="r">Adj.</th></tr>
@forelse ($d['appendix'] as $x)<tr><td><b>{{ $x['code'] }}</b> {{ $x['subject'] }}</td><td>{{ $x['title'] }}</td><td>{{ $x['date'] ?? '—' }}</td><td>{{ $x['status'] }}@if ($x['overdue']) <span class="bad">· overdue</span>@endif</td><td>{{ $x['examiner'] }}</td><td>{{ $x['moderator'] }}</td><td class="r">{{ $x['candidates'] ?? '—' }}</td><td class="r">{{ $x['pass_rate'] ?? '—' }}</td><td class="r">{{ $x['adjust'] ? 'Yes' : 'No' }}</td></tr>@empty<tr><td colspan="9" class="muted">No assessments match these filters.</td></tr>@endforelse</table>

<div class="sig">Head of Department: ______________________________ &nbsp;&nbsp; Date: ________________<br><span class="muted">{{ $m['hod'] }}</span></div>
</body></html>
