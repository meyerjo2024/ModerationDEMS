<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
  @page { margin: 48px 52px 64px 52px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1d1d1f; line-height: 1.5; }
  h1 { font-size: 25px; margin: 4px 0 2px; letter-spacing: -0.5px; }
  h2 { font-size: 14px; margin: 0; padding-bottom: 5px; border-bottom: 0.6px solid #d2d2d7; }
  .kick { font-size: 7px; letter-spacing: 1.2px; text-transform: uppercase; font-weight: bold; color: #0b4ea2; margin-top: 20px; }
  .muted { color: #6e6e73; }
  .inst { font-size: 7.5px; letter-spacing: 1.4px; text-transform: uppercase; font-weight: bold; color: #6e6e73; }
  .rule { width: 44px; height: 3px; background: #f58220; margin: 8px 0 14px; }
  table { width: 100%; border-collapse: collapse; }
  .facts td { width: 50%; vertical-align: top; padding: 0 0 8px 0; }
  .facts .k { font-size: 6.5px; letter-spacing: 1px; text-transform: uppercase; font-weight: bold; color: #6e6e73; }
  .grid th { background: #f5f5f7; text-align: left; font-size: 6.8px; letter-spacing: .8px; text-transform: uppercase; color: #6e6e73; padding: 6px; }
  .grid td { padding: 6px; border-bottom: 0.5px solid #d2d2d7; vertical-align: top; }
  .grid th.r, .grid td.r { text-align: right; } .grid th.c, .grid td.c { text-align: center; }
  .tiles td { width: 20%; padding: 0 3px; } .tiles td:first-child { padding-left: 0; } .tiles td:last-child { padding-right: 0; }
  .tile { background: #f5f5f7; border: 0.5px solid #d2d2d7; padding: 8px 9px; }
  .tile .k { font-size: 6px; letter-spacing: 1px; text-transform: uppercase; font-weight: bold; color: #6e6e73; }
  .tile .v { font-size: 16px; font-weight: bold; margin-top: 3px; }
  .chart td { vertical-align: bottom; text-align: center; padding: 0 4px; height: 92px; }
  .bar { margin: 0 auto; width: 100%; }
  .sig { border: 0.6px solid #d2d2d7; padding: 9px 11px; height: 104px; }
  .sig .lbl { font-size: 6.5px; letter-spacing: 1px; text-transform: uppercase; font-weight: bold; color: #0b4ea2; }
  .box { background: #f5f5f7; padding: 10px 12px; margin-top: 16px; }
  .dot { display: inline-block; width: 7px; height: 7px; border-radius: 4px; background: #21a04b; }
  .nb { page-break-inside: avoid; }
</style></head>
<body>
<div class="inst">{{ $institution }}</div>
<h1>Academic Moderation Report</h1>
<div class="muted" style="font-size:12px">{{ $subject->code }} · {{ $subject->name }} · {{ $number }}</div>
<div class="rule"></div>

<table class="facts">
  <tr><td><div class="k">Subject</div>{{ $subject->code }} — {{ $subject->name }}</td><td><div class="k">Assessment</div>{{ $number }}</td></tr>
  <tr><td><div class="k">Examiner</div>{{ $examiner }}</td><td><div class="k">Internal moderator</div>{{ $internal }}</td></tr>
  <tr><td><div class="k">External moderator</div>{{ $external ?? 'Not required' }}</td><td><div class="k">Head of Department</div>{{ $hod }}</td></tr>
</table>

<div class="kick">Section 1 · Pre-assessment</div><h2>Types of questions</h2>
<table class="grid" style="margin-top:6px"><thead><tr><th>Question type</th><th class="r">Weighting</th><th class="c">HEQF level</th><th class="c">Aligned</th><th>Comment</th></tr></thead><tbody>
  @foreach ($questionTypes as $q)<tr><td>{{ $q['type'] }}</td><td class="r">{{ $q['weighting'] + 0 }}%</td><td class="c">{{ $q['heqf_level'] }}</td><td class="c">{{ $q['aligned'] ? 'Yes' : 'No' }}</td><td>{{ $q['comment'] ?? '' }}</td></tr>@endforeach
</tbody></table>
<div class="muted" style="margin-top:4px">Total weighting: {{ round(collect($questionTypes)->sum('weighting'), 2) }}%</div>

@if ($docs)
  <div class="kick">Evidence</div><h2>Documents reviewed</h2>
  <table class="grid" style="margin-top:6px"><thead><tr><th>Document</th><th>File</th><th>Uploaded (UTC)</th><th>SHA-256 fingerprint</th></tr></thead><tbody>
    @foreach ($docs as $d)<tr><td>{{ $d['label'] }}</td><td>{{ $d['filename'] }}</td><td>{{ $d['at']->utc()->format('Y-m-d H:i') }}</td><td style="font-size:7px">{{ substr($d['sha'], 0, 32) }}…</td></tr>@endforeach
  </tbody></table>
@endif

@if ($stats)
  <div class="nb">
    <div class="kick">Section 2 · Post-assessment</div><h2>Performance statistics</h2>
    <table class="tiles" style="margin-top:8px"><tr>
      @foreach ([['Candidates', $stats['candidates']], ['Pass rate', number_format($stats['pass_rate'], 1).'%'], ['Class average', number_format($stats['average'], 1).'%'], ['Highest', number_format($stats['highest'], 1).'%'], ['Lowest', number_format($stats['lowest'], 1).'%']] as [$k, $v])
        <td><div class="tile"><div class="k">{{ $k }}</div><div class="v">{{ $v }}</div></div></td>
      @endforeach
    </tr></table>
    <div class="muted" style="margin-top:6px">{{ $stats['passed'] }} of {{ $stats['candidates'] }} candidates achieved {{ $stats['pass_mark'] }}% or higher. Marks are expressed as percentages of {{ $stats['total_marks'] + 0 }} total marks.@if ($stats['invalid']) {{ $stats['invalid'] }} non-numeric / out-of-range {{ $stats['invalid'] === 1 ? 'entry was' : 'entries were' }} excluded.@endif<br>@if ($stats['source'])Source: {{ $stats['source'] }}@endif</div>
    @php $max = max(1, ...$stats['distribution']); @endphp
    <table class="chart" style="margin-top:10px"><tr>
      @foreach ($stats['distribution'] as $i => $n)
        <td>@if ($n)<div style="font-size:7.5px">{{ $n }}</div>@endif<div class="bar" style="height: {{ max($n / $max * 78, 1) }}px; background: {{ $i >= 5 ? '#0b4ea2' : '#9a9aa2' }}"></div></td>
      @endforeach
    </tr><tr>@foreach ($stats['distribution'] as $i => $n)<td style="height:auto;font-size:6.5px;color:#6e6e73;border-top:0.6px solid #d2d2d7">{{ $i === 9 ? '90–100' : ($i * 10).'–'.($i * 10 + 9) }}</td>@endforeach</tr></table>
    <div class="muted" style="font-size:7.5px;margin-top:3px">Mark distribution (% of total, number of candidates per band)</div>
  </div>
  @if ($commentary)<div class="nb"><div class="kick">Section 2</div><h2>Examiner commentary</h2><p style="margin-top:6px">{!! nl2br(e($commentary)) !!}</p></div>@endif
@endif

@foreach ($reviews as $r)
  <div class="nb">
    <div class="kick">Moderation</div><h2>{{ $r['label'] }}</h2>
    <table class="facts" style="margin-top:8px"><tr><td><div class="k">Reviewer</div>{{ $r['reviewer'] }}</td><td><div class="k">Outcome</div>Approved</td></tr>
      <tr><td><div class="k">Consensus reached</div>{{ $r['consensus'] ? 'Yes' : 'No' }}</td><td><div class="k">Date</div>{{ $r['at']->utc()->format('Y-m-d H:i') }} UTC</td></tr>
      @if ($r['scripts'] !== null)<tr><td><div class="k">Scripts sampled</div>{{ $r['scripts'] }}</td><td></td></tr>@endif</table>
    @if ($r['checks'])
      <table class="grid"><thead><tr><th>Quality check</th><th class="c">Answer</th><th>Comment</th></tr></thead><tbody>
        @foreach ($r['checks'] as $c)<tr><td>{{ $c['question'] }}</td><td class="c">{{ ['YES' => 'Yes', 'NO' => 'No', 'NA' => 'N/A'][$c['answer']] ?? $c['answer'] }}</td><td>{{ $c['comment'] ?? '' }}</td></tr>@endforeach
      </tbody></table>
    @endif
    @if ($r['comments'])<div class="muted" style="margin-top:8px;font-size:7.5px;font-weight:bold">Comments</div><p style="margin:2px 0 0">{!! nl2br(e($r['comments'])) !!}</p>@endif
  </div>
@endforeach

<div class="nb">
  <div class="kick">Authenticated sign-off</div><h2>Signatures</h2>
  <p class="muted" style="font-size:8px;margin:6px 0 10px">Each signature was drawn by the signatory while signed in to their own account and confirmed with their password. The hash identifies the exact content attested to.</p>
  <table><tr>
    @foreach ($signatures as $i => $s)
      <td style="width:50%;padding:0 {{ $i % 2 === 0 ? '6px 10px 0' : '0 10px 6px' }}">
        <div class="sig"><div class="lbl">{{ $s['label'] }}</div>
          <div style="height:40px;margin:5px 0 3px;border-bottom:0.5px solid #d2d2d7"><img src="{{ $s['image'] }}" style="height:38px"></div>
          <div style="font-weight:bold;font-size:10px">{{ $s['name'] }}</div>
          <div class="muted" style="font-size:7.5px">{{ $s['role'] }} · {{ $s['at']->utc()->format('Y-m-d H:i') }} UTC</div>
          <div class="muted" style="font-size:6.5px">#{{ substr($s['hash'], 0, 24) }}</div></div>
      </td>
      @if ($i % 2 === 1 && ! $loop->last)</tr><tr>@endif
    @endforeach
    @if (count($signatures) % 2 === 1)<td></td>@endif
  </tr></table>
</div>

<div class="box nb"><span class="dot"></span> <b>Audit-compliant record</b><br>
  <span class="muted" style="font-size:7.5px">{{ $auditCount }} audit entries · chain head {{ $auditHead ? substr($auditHead, 0, 32).'…' : 'n/a' }}<br>Report {{ $reportId }} · generated {{ $generatedAt->utc()->format('Y-m-d H:i') }} UTC</span></div>
</body></html>
