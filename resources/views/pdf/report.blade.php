@php
$f = $form;
$esc = fn ($v) => e((string) ($v ?? ''));
$nl = fn ($v) => nl2br(e((string) ($v ?? '')));
$pct = fn ($v) => $v === null ? '' : number_format((float) $v, 1).'%';
$date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '';
$yn = fn ($v) => $v === 'YES' ? 'Yes' : ($v === 'NO' ? 'No' : '');
$s1q = $s1m['questions'] ?? [];
@endphp
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
  @page { margin: 34px 30px 46px 30px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #000; line-height: 1.35; }
  table { width: 100%; border-collapse: collapse; }
  td, th { border: 0.6px solid #000; padding: 3px 4px; vertical-align: top; }
  .noborder, .noborder td { border: none; }
  .g1 { background: #E7E6E6; } .g2 { background: #D9D9D9; } .blue { background: #DEEAF6; }
  .b { font-weight: bold; } .c { text-align: center; } .r { text-align: right; } .i { font-style: italic; }
  .appendix { color: #365F91; font-weight: bold; font-size: 9px; }
  .title { text-align: center; font-weight: bold; font-size: 12px; margin: 6px 0 10px; }
  .banner-green { background: #D5E2BB; border: 0.8px solid #000; font-weight: bold; padding: 6px 8px; margin: 8px 0 6px; }
  .banner-peach { background: #FAE3D4; border: 0.8px solid #000; font-weight: bold; padding: 6px 8px; text-align: center; margin: 12px 0 0; }
  .sel { background: #0a1f44; color: #fff; font-weight: bold; }
  .box { border: 0.6px solid #000; min-height: 12px; }
  .small { font-size: 7px; }
  .sigcell { height: 34px; vertical-align: middle; text-align: center; }
  .sigcell img { height: 28px; }
  .nb { page-break-inside: avoid; }
  .ans { font-weight: bold; }
  .elec { margin-top: 14px; border: 0.6px solid #888; background: #f7f7f7; padding: 6px 8px; font-size: 6.8px; color: #444; }
</style></head>
<body>

{{-- ───────────────────────── SECTION 1 ───────────────────────── --}}
<div class="appendix">{{ $f['appendix'] }}</div>
@if ($logo)<div style="margin-top:2px"><img src="{{ $logo }}" style="height:46px"></div>@endif
<div class="title">{{ $f['title'] }}</div>

<div class="banner-green">SECTION 1 (Pre-assessment): Jointly completed by the Examiner &amp; Subject Moderator/s</div>

<table style="margin-bottom:5px"><tr>
  <td class="g1" style="width:11%">ASSESSMENT:</td>
  @foreach ($f['periods'] as $k => $label)
    <td style="width:17%">{{ $label }}: <span class="b" style="float:right">{{ ($s1e['period'] ?? null) === $k ? 'X' : '' }}</span></td>
  @endforeach
  <td class="g1" style="width:8%">YEAR:</td><td style="width:10%" class="b">{{ $esc($s1e['year'] ?? '') }}</td>
</tr></table>

<table style="margin-bottom:6px">
  <tr><td class="g1 b" style="width:17%">Examiner:</td><td style="width:33%">{{ $names['examiner'] }}</td><td class="g1 b" style="width:25%">Assessment No:</td><td>{{ $number }}</td></tr>
  <tr><td class="g1 b">Internal Moderator:</td><td>{{ $names['internal'] }}</td><td class="g1 b">HEQF level of subject:</td><td>{{ $esc($s1e['heqf_level'] ?? '') }}</td></tr>
  <tr><td class="g1 b">External Moderator:</td><td>{{ $names['external'] ?? '' }}</td><td class="g1 b">Level of subject: (e.g. YR 1, YR 2 etc):</td><td>{{ $esc($s1e['subject_level'] ?? '') }}</td></tr>
  <tr><td class="g1 b">Subject name:</td><td>{{ $subject->name }}</td><td class="g1 b">Subject Code:</td><td>{{ $subject->code }}</td></tr>
  <tr><td class="g1 b">Qualification</td><td>{{ $esc($s1e['qualification'] ?? '') }}</td><td class="g1 b">Qualification code:</td><td>{{ $esc($s1e['qualification_code'] ?? '') }}</td></tr>
  <tr><td class="g1 b">Assessment Date:</td><td colspan="3">{{ $date($s1e['assessment_date'] ?? null) }}</td></tr>
</table>

<table style="margin-bottom:3px">
  <tr class="g2 b c">
    <td style="width:42%">Types of questions (or project assessment criteria) used in this assessment task</td>
    <td style="width:8%">% Weighting across task</td><td style="width:27%"></td>
    <td style="width:7%">Poor</td><td style="width:8%">Adequate</td><td style="width:8%">Good</td>
  </tr>
  @php $ratingKeys = array_keys($f['ratings']); $types = array_keys($f['question_types']); @endphp
  @foreach ($types as $i => $tk)
    @php $rk = $ratingKeys[$i] ?? null; $val = $rk !== null ? ($s1m['ratings'][$rk] ?? null) : null; @endphp
    <tr>
      <td class="g1" style="font-size:7.2px">{{ $f['question_types'][$tk]['text'] }}</td>
      <td class="c b" style="vertical-align:middle">@isset($s1e['weights'][$tk]){{ $s1e['weights'][$tk] + 0 }}%@endisset</td>
      <td class="blue" style="vertical-align:middle">{{ $rk ? $f['ratings'][$rk] : '' }}</td>
      @foreach ([0, 1, 2] as $n)
        <td class="c {{ $rk && $val === $n ? 'sel' : '' }}" style="vertical-align:middle">{{ $rk ? $n : '' }}</td>
      @endforeach
    </tr>
  @endforeach
  <tr class="g1 b"><td class="r">Total</td><td class="c">{{ isset($s1e['weights']) ? round(array_sum($s1e['weights']), 2).'%' : '' }}</td><td colspan="4"></td></tr>
</table>
<div class="small i">[Adapted from Bloom’s taxonomy to incorporate the SAQA level descriptors]</div>
<div class="small b" style="margin-bottom:6px">Table: levels of complexity of assessments</div>

<table class="nb" style="margin-bottom:4px">
  @foreach ($f['s1_questions'] as $k => $text)
    <tr><td class="blue b" style="width:4%">{{ substr($k, 1) }}.</td><td class="blue">{{ $text }}</td><td class="c ans" style="width:9%;vertical-align:middle">{{ $yn($s1q[$k]['answer'] ?? null) }}</td></tr>
    <tr><td></td><td colspan="2"><span class="i">Comments:</span> {!! $nl($s1q[$k]['comment'] ?? '') !!}</td></tr>
  @endforeach
</table>

@include('pdf.declaration', ['rows' => [['Examiner', $sig['s1']['examiner']], ['Internal Moderator', $sig['s1']['internal']], ['External Moderator', $hasExternal ? $sig['s1']['external'] : null, ! $hasExternal]]])

{{-- ───────────────────────── SECTION 2 ───────────────────────── --}}
<div style="page-break-before: always"></div>
<div class="banner-green">SECTION 2 (Post-assessment): Jointly completed by the Examiner &amp; Subject Moderator/s</div>

<table style="margin-bottom:8px">
  <tr><td class="blue b" style="width:26%">{{ $subject->code }}</td><td colspan="3" class="b">{{ $subject->name }}</td></tr>
  <tr><td class="g1 b">Total number of (reg.) candidates</td><td style="width:22%">{{ $esc($stats['registered']) }}</td><td class="g1 b" style="width:26%">No. of candidates absent:</td><td>{{ $esc($stats['absent']) }}</td></tr>
  <tr><td class="g1 b">Highest mark obtained:</td><td>{{ $pct($stats['highest']) }}</td><td class="g1 b">No. of passes:</td><td>{{ $esc($stats['passes']) }}</td></tr>
  <tr><td class="g1 b">Percentage pass:</td><td>{{ $pct($stats['pass_rate']) }}</td><td class="g1 b">Average mark (%):</td><td>{{ $pct($stats['average']) }}</td></tr>
  <tr><td class="g1 b">Type of assessment:</td><td colspan="3">{{ $esc($s2e['type_of_assessment'] ?? '') }}</td></tr>
</table>

<table style="margin-bottom:4px">
  @foreach ($f['s2_examiner_questions'] as $k => $text)
    <tr><td class="blue b" style="width:4%">{{ substr($k, 1) }}.</td><td class="blue">{{ $text }}</td></tr>
    <tr><td></td><td><span class="i">Comments:</span> {!! $nl($s2e['answers'][$k] ?? '') !!}</td></tr>
  @endforeach
  @foreach ($f['s2_moderator_questions'] as $k => $text)
    <tr><td class="blue b">{{ substr($k, 1) }}.</td><td class="blue">{{ $text }}</td></tr>
    <tr><td></td><td><span class="i">Comments:</span> {!! $nl($s2m['answers'][$k] ?? '') !!}</td></tr>
  @endforeach
  <tr><td class="blue b">8.</td><td class="blue">Please comment on the following:</td></tr>
</table>
<table style="margin-bottom:4px">
  @foreach ($f['comment_items'] as $k => $text)
    <tr><td><span class="i">{{ $text }}</span><br>{!! $nl($s2m['items'][$k] ?? '') !!}</td></tr>
  @endforeach
  <tr><td class="blue b">{{ $f['adjustments'] }} <span class="ans" style="float:right">{{ $yn($s2m['adjustments']['recommended'] ?? null) }}</span></td></tr>
  <tr><td><span class="i">Specify:</span> {!! $nl($s2m['adjustments']['specify'] ?? '') !!}</td></tr>
</table>

@include('pdf.declaration', ['rows' => [['Examiner', $sig['s2']['examiner']], ['Internal Moderator', $sig['s2']['internal']], ['External Moderator', $hasExternal ? $sig['s2']['external'] : null, ! $hasExternal]]])

{{-- ───────────────────────── SECTION 3 ───────────────────────── --}}
<div style="page-break-before: always"></div>
<div class="banner-green">SECTION 3: To be completed by the External Moderator (where applicable)<br><span class="i b" style="font-weight:normal">[Sections 1 and 2 to be included for the external moderator]</span></div>

@if ($hasExternal)
  <table style="margin-bottom:4px">
    <tr><td class="blue b">Please comment on the following:</td></tr>
    @foreach ($f['comment_items'] + $f['s3_extra_items'] as $k => $text)
      <tr><td><span class="i">{{ $text }}</span><br>{!! $nl($s3['items'][$k] ?? '') !!}</td></tr>
    @endforeach
    <tr><td class="blue b">{{ $f['adjustments'] }} <span class="ans" style="float:right">{{ $yn($s3['adjustments']['recommended'] ?? null) }}</span></td></tr>
    <tr><td><span class="i">Specify:</span> {!! $nl($s3['adjustments']['specify'] ?? '') !!}</td></tr>
  </table>

  <table class="nb" style="margin-top:10px">
    @foreach ([['External Moderator', $sig['s3']['external']], ['Examiner', $sig['s3']['examiner']], ['Head of Department', $sig['s3']['hod']]] as [$role, $s])
      <tr class="g2 b c"><td style="width:46%;text-align:left">NAME: {{ $role }} (please print)</td><td style="width:30%">SIGNATURE</td><td>DATE</td></tr>
      <tr><td class="sigcell b" style="text-align:left">{{ $s['name'] ?? '' }}</td><td class="sigcell">@if ($s)<img src="{{ $s['image'] }}">@endif</td><td class="sigcell">{{ $s['date'] ?? '' }}</td></tr>
    @endforeach
  </table>
@else
  <table><tr><td class="c i" style="padding:14px">Not applicable — no external moderator was assigned to this assessment.</td></tr></table>
@endif

<div class="elec nb">
  <b>Electronic record.</b> Signatures were drawn by each signatory while signed in to their own account and confirmed with their password; each signature is stored with a hash of the content it attests to.
  Report {{ $reportId }} · generated {{ $generatedAt->utc()->format('d/m/Y H:i') }} UTC · {{ $auditCount }} audit entries · chain head {{ $auditHead ? substr($auditHead, 0, 24).'…' : 'n/a' }}.
  @if ($docs)<br>Documents reviewed:
    @foreach ($docs as $d){{ $d['label'] }} “{{ $d['filename'] }}” (SHA-256 {{ substr($d['sha'], 0, 16) }}…){{ $loop->last ? '.' : '; ' }}@endforeach
  @endif
  @if ($stats['source'])<br>Marks source: {{ $stats['source'] }}@if ($stats['weight']) · test weight {{ $stats['weight'] + 0 }}% of the year mark @endif.@endif
  @if (($stats['invalid'] ?? 0) > 0)<br>{{ $stats['invalid'] }} non-numeric / out-of-range entr{{ $stats['invalid'] === 1 ? 'y was' : 'ies were' }} excluded from the statistics.@endif
</div>
</body></html>
