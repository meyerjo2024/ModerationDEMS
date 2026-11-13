<x-app-layout title="Overview report" wide>
@php $N = $d['narrative'] ?? []; $para = fn ($k) => ! empty($N[$k]) ? '<div class="mt-3 space-y-2 text-[15px] leading-relaxed">'.collect(preg_split('/\R{2,}|\R/', $N[$k]))->filter()->map(fn ($p) => '<p>'.e($p).'</p>')->implode('').'</div>' : ''; $m = $d['meta']; $h = $d['headline']; $c = $d['compliance']; $q = $d['quality']; $r = $d['results']; $e = $d['exceptions']; @endphp
<div class="space-y-8">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div><a href="{{ route('overview') }}" class="text-sm font-semibold text-accent">← Overview</a><p class="kicker mt-3">Head of Department</p><h1 class="mt-1 text-3xl font-semibold">Report for the Dean</h1>
      <p class="mt-1 text-sm text-[var(--ink-2)]">All your subjects at a glance. Always up to date — no student names or individual marks. Separate from the moderation report of each assessment.</p></div>
    <form method="POST" class="flex gap-2" x-data>@csrf
      @foreach ($f as $fk => $fv)@if ($fv !== null && $fv !== '')<input type="hidden" name="{{ $fk }}" value="{{ $fv }}">@endif @endforeach
      <template x-for="(v, k) in ($store.dean.sections || {})" :key="k"><input type="hidden" :name="'sections[' + k + ']'" :value="v"></template>
      <button type="submit" formaction="{{ route('dean-report.pdf') }}" class="btn-primary"><x-icon name="download" /> PDF</button><button type="submit" formaction="{{ route('dean-report.docx') }}" class="btn-secondary"><x-icon name="download" /> Word</button></form>
  </div>

  <form method="GET" action="{{ route('dean-report') }}" class="card grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
    <p class="kicker sm:col-span-2 lg:col-span-4">What to include</p>
    <div><label class="label">Academic year</label><select name="year" class="field"><option value="">All years</option>@foreach ($years as $y)<option value="{{ $y }}" @selected(($f['year'] ?? null) == $y)>{{ $y }}</option>@endforeach</select></div>
    <div><label class="label">Qualification</label><select name="qualification" class="field"><option value="">All</option>@foreach ($quals as $x)<option @selected($f['qualification'] === $x)>{{ $x }}</option>@endforeach</select></div>
    <div><label class="label">Assessments</label><select name="kind" class="field"><option value="all">All</option><option value="tests" @selected($f['kind'] === 'tests')>Tests (T1–T7)</option><option value="additional" @selected($f['kind'] === 'additional')>Supplementary, special, sick, deferred</option></select></div>
    <div><label class="label">Report for</label><input name="to_whom" class="field" value="{{ $f['to_whom'] }}" placeholder="The Dean"></div>
    <div><label class="label">Assessments written from</label><input type="date" name="from" value="{{ $f['from'] }}" class="field"></div>
    <div><label class="label">to</label><input type="date" name="to" value="{{ $f['to'] }}" class="field"></div>
    <div class="sm:col-span-2"><label class="label">Your comment <span class="font-normal normal-case text-slate-400">(printed at the top)</span></label><input name="comment" value="{{ $f['comment'] }}" maxlength="3000" class="field"></div>
    <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-4"><button class="btn-primary">Update report</button><a href="{{ route('dean-report') }}" class="text-sm font-semibold text-accent">Clear</a></div>
  </form>

  <section class="card space-y-4 p-6 sm:p-8" x-data="deanNarrative(@js(['sections' => collect($sections)->map(fn ($l, $k) => $N[$k] ?? '')->all(), 'generate' => route('dean-report.narrative'), 'save' => route('dean-report.narrative.save')]))">
    <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="kicker">Narrative</p><h2 class="mt-1 text-xl font-semibold">Discussion for the Dean</h2>
      <p class="mt-1 max-w-3xl text-sm text-[var(--ink-2)]">Let the AI write the discussion from the figures below, or start from a plain draft, then edit every paragraph. The PDF and Word report show your text above the supporting figures.</p></div>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="btn-primary" @click="generate('ai')" :disabled="!!busy" @if (! $groq) title="Add GROQ_API_KEY in Render to switch this on" disabled @endif><span x-text="busy === 'ai' ? 'Writing…' : 'Write with AI'"></span></button>
        <button type="button" class="btn-secondary" @click="generate('draft')" :disabled="!!busy"><span x-text="busy === 'draft' ? 'Drafting…' : 'Quick draft (no AI)'"></span></button></div></div>
    @if (! $groq)<x-notice tone="warn">The AI writer is not switched on yet. Add <b>GROQ_API_KEY</b> in Render → Environment and redeploy. “Quick draft” works without it.</x-notice>@endif
    <p class="text-xs text-[var(--ink-3)]">Privacy: the AI receives only summary figures, subject codes and the examiners’ written comments (e-mail addresses removed) — no student data and no staff names. Check the text before you send it on.</p>
    <template x-if="error"><x-notice tone="error"><span x-text="error"></span></x-notice></template>
    <template x-if="msg"><x-notice tone="success"><span x-text="msg"></span></x-notice></template>
    <div class="grid gap-4">@foreach ($sections as $k => $label)
      <div><label class="label" for="nar-{{ $k }}">{{ $label }}</label><textarea id="nar-{{ $k }}" rows="{{ $k === 'summary' ? 4 : 3 }}" maxlength="4000" class="field text-sm" x-model="sections.{{ $k }}"></textarea></div>
    @endforeach</div>
    <div class="flex flex-wrap items-center justify-end gap-3">@if (! empty($N))<span class="mr-auto text-xs font-semibold text-emerald-600">✓ A narrative is included in the report</span>@endif
      <button type="button" class="text-sm font-semibold text-rose-600" @click="save(true)" :disabled="!!busy">Remove narrative</button>
      <button type="button" class="btn-primary" @click="save()" :disabled="!!busy"><span x-text="busy === 'save' ? 'Saving…' : 'Save into report'"></span></button></div>
  </section>

  <section class="card p-6 sm:p-8">
    <p class="text-xs text-[var(--ink-3)]">{{ $m['institution'] }}@if ($m['department']) · {{ $m['department'] }}@endif</p>
    <h2 class="mt-1 text-2xl font-semibold">Assessment moderation overview</h2>
    <p class="mt-1 text-sm text-[var(--ink-2)]">For: {{ $m['to_whom'] }} · {{ $m['period'] }} · {{ $m['qualification'] }} · {{ $m['kind'] }}<br>Prepared by {{ $m['hod'] }} · {{ $m['generated'] }}</p>
    @if ($m['comment'])<p class="mt-4 whitespace-pre-wrap rounded-xl bg-[var(--fog)] p-4 text-sm"><b>Head of Department’s comment:</b> {{ $m['comment'] }}</p>@endif

    {!! $para('summary') !!}
    <h3 class="mt-8 text-lg font-semibold">1. At a glance <span class="text-sm font-normal text-[var(--ink-3)]">(numbers only)</span></h3>
    @php $tot = fn ($rows, $k) => collect($rows)->sum($k); @endphp
    <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[40rem] text-sm"><thead class="text-left text-xs text-[var(--ink-3)]"><tr><th class="py-2">Qualification code</th><th class="text-right">Subjects</th><th class="text-right">Assessments</th><th class="text-right">Completed</th><th class="text-right">Pending</th><th class="text-right">In process</th><th class="text-right">Overdue</th><th class="text-right">No moderator</th><th class="text-right">No examiner</th></tr></thead>
      <tbody class="divide-y divide-[var(--divider)]">@forelse ($d['by_code'] as $x)<tr><td class="py-2 pr-3 font-semibold">{{ $x['code'] }}</td><td class="text-right tabular-nums">{{ $x['subjects'] }}</td><td class="text-right tabular-nums">{{ $x['assessments'] }}</td><td class="text-right tabular-nums text-emerald-600">{{ $x['completed'] }}</td><td class="text-right tabular-nums">{{ $x['pending'] }}</td><td class="text-right tabular-nums">{{ $x['in_process'] }}</td><td class="text-right tabular-nums {{ $x['overdue'] ? 'font-semibold text-rose-600' : '' }}">{{ $x['overdue'] }}</td><td class="text-right tabular-nums {{ $x['no_moderator'] ? 'text-rose-600' : '' }}">{{ $x['no_moderator'] }}</td><td class="text-right tabular-nums {{ $x['no_examiner'] ? 'text-amber-600' : '' }}">{{ $x['no_examiner'] }}</td></tr>@empty<tr><td colspan="9" class="py-4 text-[var(--ink-3)]">No subjects.</td></tr>@endforelse</tbody>
      <tfoot><tr class="border-t border-[var(--ink-3)] font-semibold"><td class="py-2">Total</td><td class="text-right tabular-nums">{{ $h['subjects'] }}</td><td class="text-right tabular-nums">{{ $h['assessments'] }}</td><td class="text-right tabular-nums">{{ $h['completed'] }}</td><td class="text-right tabular-nums">{{ $h['pending'] }}</td><td class="text-right tabular-nums">{{ $h['in_process'] }}</td><td class="text-right tabular-nums">{{ $h['overdue'] }}</td><td class="text-right tabular-nums">{{ count($h['no_moderator']) }}</td><td class="text-right tabular-nums">{{ count($h['no_examiner']) }}</td></tr></tfoot></table></div>
    <div class="mt-4 overflow-x-auto"><table class="w-full min-w-[30rem] text-sm"><thead class="text-left text-xs text-[var(--ink-3)]"><tr><th class="py-2">Term</th><th class="text-right">Assessments</th><th class="text-right">Completed</th><th class="text-right">Pending</th><th class="text-right">In process</th><th class="text-right">Overdue</th></tr></thead>
      <tbody class="divide-y divide-[var(--divider)]">@foreach ($d['by_term'] as $x)<tr><td class="py-2 pr-3 font-semibold">{{ $x['label'] }}</td><td class="text-right tabular-nums">{{ $x['assessments'] }}</td><td class="text-right tabular-nums text-emerald-600">{{ $x['completed'] }}</td><td class="text-right tabular-nums">{{ $x['pending'] }}</td><td class="text-right tabular-nums">{{ $x['in_process'] }}</td><td class="text-right tabular-nums {{ $x['overdue'] ? 'font-semibold text-rose-600' : '' }}">{{ $x['overdue'] }}</td></tr>@endforeach</tbody></table></div>
    <p class="mt-2 text-xs text-[var(--ink-3)]">Terms follow the month of the assessment date (Term 1 Jan–Mar, Term 2 Apr–Jun, Term 3 Jul–Sep, Term 4 Oct–Dec). Pending = waiting on a moderator or signatory; in process = with the examiner.</p>

    <h3 class="mt-8 text-lg font-semibold">2. Moderation compliance</h3>{!! $para('compliance') !!}
    <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[30rem] text-sm"><tbody class="divide-y divide-[var(--divider)]">
      <tr><td class="py-2 pr-4">Moderated before the students wrote — Yes / No / not yet recorded</td><td class="py-2 text-right font-semibold tabular-nums">{{ $c['pre_yes'] }} / {{ $c['pre_no'] }} / {{ $c['pre_unknown'] }}</td></tr>
      <tr><td class="py-2 pr-4">Completed within the post-moderation window / late</td><td class="py-2 text-right font-semibold tabular-nums">{{ $c['on_time'] }} / {{ $c['late'] }}</td></tr>
      <tr><td class="py-2 pr-4">Records needing revision after pre-moderation</td><td class="py-2 text-right font-semibold tabular-nums">{{ $c['needing_revision'] }}</td></tr>
      <tr><td class="py-2 pr-4">Average revisions per submitted record</td><td class="py-2 text-right font-semibold tabular-nums">{{ $c['avg_revisions'] ?? '—' }}</td></tr></tbody></table></div>
    @if ($c['late_list'])<p class="mt-2 text-xs text-[var(--ink-3)]">Late: {{ implode('; ', $c['late_list']) }}</p>@endif

    <h3 class="mt-8 text-lg font-semibold">3. Assessment quality <span class="text-sm font-normal text-[var(--ink-3)]">(internal moderators’ ratings: 0 poor · 1 adequate · 2 good)</span></h3>{!! $para('quality') !!}
    <div class="mt-3 space-y-2">@foreach ($q['areas'] as $a)
      <div class="grid items-center gap-3 sm:grid-cols-[minmax(0,1fr)_220px_90px]"><span class="text-sm">{{ $a['label'] }}</span>
        <div class="h-2 overflow-hidden rounded-full bg-[var(--fog)]"><div class="h-full rounded-full bg-accent" style="width: {{ $a['avg'] !== null ? round($a['avg'] / 2 * 100) : 0 }}%"></div></div>
        <span class="text-sm tabular-nums">{{ $a['avg'] ?? '—' }} <span class="text-xs text-[var(--ink-3)]">({{ $a['n'] }}{{ $a['poor'] ? ', '.$a['poor'].' poor' : '' }})</span></span></div>
    @endforeach</div>
    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-[var(--ink-2)]">@foreach ($q['yn'] as $a)<li>{{ $a['label'] }} — <b>{{ $a['yes'] }} yes</b>, {{ $a['no'] }} no</li>@endforeach
      @foreach ($q['poor'] as $p)<li class="text-rose-700">Rated poor: {{ $p['name'] }} ({{ implode(', ', $p['areas']) }})</li>@endforeach</ul>

    <h3 class="mt-8 text-lg font-semibold">4. Student results <span class="text-sm font-normal text-[var(--ink-3)]">(anonymous)</span></h3>{!! $para('results') !!}
    <p class="mt-2 text-sm">Overall: {{ $r['overall']['assessments'] }} assessment(s) with results, {{ $r['overall']['candidates'] }} candidate results{{ $r['overall']['pass_rate'] !== null ? ', pass rate '.$r['overall']['pass_rate'].'%' : '' }}.</p>
    @foreach ([['Qualification code', $r['by_qualification']], ['Term', $r['by_term']], ['Year level', $r['by_level']]] as [$title, $set])
      @if ($set)<div class="mt-3 overflow-x-auto"><table class="w-full min-w-[34rem] text-sm"><thead class="text-left text-xs text-[var(--ink-3)]"><tr><th class="py-2">{{ $title }}</th><th class="text-right">Assessments</th><th class="text-right">Candidates</th><th class="text-right">Pass rate</th><th class="text-right">Class average</th><th class="text-right">Absent</th></tr></thead>
        <tbody class="divide-y divide-[var(--divider)]">@foreach ($set as $x)<tr><td class="py-2 pr-3">{{ $x['name'] }}</td><td class="text-right tabular-nums">{{ $x['assessments'] }}</td><td class="text-right tabular-nums">{{ $x['candidates'] }}</td><td class="text-right font-semibold tabular-nums">{{ $x['pass_rate'] !== null ? $x['pass_rate'].'%' : '—' }}</td><td class="text-right tabular-nums">{{ $x['average'] }}%</td><td class="text-right tabular-nums">{{ $x['absent'] }}</td></tr>@endforeach</tbody></table></div>@endif
    @endforeach
    @if ($r['low'])<p class="mt-4 text-sm font-semibold">Pass rate below 50%</p><ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-rose-700">@foreach ($r['low'] as $x)<li>{{ $x['name'] }} — {{ $x['pass_rate'] }}% ({{ $x['candidates'] }} candidates)</li>@endforeach</ul>@endif
    @if ($r['drops'])<p class="mt-4 text-sm font-semibold">Pass rate fell by 10 points or more since the previous assessment</p><ul class="mt-1 list-disc space-y-1 pl-5 text-sm">@foreach ($r['drops'] as $x)<li>{{ $x['name'] }}: {{ $x['text'] }}</li>@endforeach</ul>@endif
    @if ($r['subminimum']['assessments'])<p class="mt-4 text-sm"><b>Subminimum:</b> {{ $r['subminimum']['assessments'] }} assessment(s) have one; {{ $r['subminimum']['not_met'] }} of {{ $r['subminimum']['students'] }} students did not meet it ({{ $r['subminimum']['passed_below'] }} of them had reached the pass mark).</p>@endif

    <h3 class="mt-8 text-lg font-semibold">5. Matters for attention</h3>{!! $para('attention') !!}
    <p class="mt-2 text-sm font-semibold">General mark adjustments recommended ({{ count($e['adjustments']) }})</p>
    <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">@forelse ($e['adjustments'] as $x)<li>{{ $x['name'] }}@if ($x['why']): {{ $x['why'] }}@endif</li>@empty<li class="list-none text-[var(--ink-3)]">None.</li>@endforelse</ul>
    <p class="mt-3 text-sm font-semibold">Overdue ({{ count($e['overdue']) }})</p>
    <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">@forelse ($e['overdue'] as $x)<li>{{ $x['name'] }} — {{ $x['status'] }} (examiner: {{ $x['examiner'] }})</li>@empty<li class="list-none text-[var(--ink-3)]">None.</li>@endforelse</ul>
    <p class="mt-3 text-sm font-semibold">Subjects without an internal moderator ({{ count($h['no_moderator']) }})</p>
    <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">@forelse ($h['no_moderator'] as $x)<li>{{ $x }}</li>@empty<li class="list-none text-[var(--ink-3)]">None.</li>@endforelse</ul>
    <p class="mt-3 text-sm font-semibold">Subjects without an examiner ({{ count($h['no_examiner']) }})</p>
    <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">@forelse ($h['no_examiner'] as $x)<li>{{ $x }}</li>@empty<li class="list-none text-[var(--ink-3)]">None.</li>@endforelse</ul>
    <p class="mt-3 text-sm font-semibold">External moderators’ recommendations ({{ count($e['external']) }})</p>
    <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">@forelse ($e['external'] as $x)<li>{{ $x['name'] }}: {{ $x['text'] }}</li>@empty<li class="list-none text-[var(--ink-3)]">None recorded.</li>@endforelse</ul>

    <h3 class="mt-8 text-lg font-semibold">6. What examiners will do differently next time</h3>{!! $para('actions') !!}
    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">@forelse ($d['actions'] as $x)<li><b>{{ $x['name'] }}:</b> {{ $x['text'] }}</li>@empty<li class="list-none text-[var(--ink-3)]">None recorded.</li>@endforelse</ul>

    <h3 class="mt-8 text-lg font-semibold">Appendix: all assessments by term ({{ count($d['appendix']) }})</h3>
    @php $byTerm = collect($d['appendix'])->groupBy('term'); @endphp
    @if (! count($d['appendix']))<p class="mt-3 text-sm text-[var(--ink-3)]">No assessments match these filters.</p>@endif
    @forelse ([1, 2, 3, 4, 0] as $tn)
      @php $rowsT = $byTerm->get($tn, collect()); @endphp
      @continue($tn === 0 && $rowsT->isEmpty())
      <p class="mt-5 text-sm font-semibold">{{ $tn ? 'Term '.$tn : 'No assessment date' }} <span class="font-normal text-[var(--ink-3)]">· {{ $rowsT->count() }} assessment(s)</span></p>
      <div class="mt-2 overflow-x-auto"><table class="w-full min-w-[48rem] text-sm"><thead class="text-left text-xs text-[var(--ink-3)]"><tr><th class="py-2">Subject</th><th>Assessment</th><th>Date</th><th>Status</th><th>Examiner</th><th>Moderator</th><th class="text-right">Cand.</th><th class="text-right">Pass %</th><th class="text-right">Adj.</th></tr></thead>
        <tbody class="divide-y divide-[var(--divider)]">@forelse ($rowsT as $x)<tr><td class="py-2 pr-3"><b>{{ $x['code'] }}</b> {{ $x['subject'] }}</td><td>{{ $x['title'] }}</td><td>{{ $x['date'] ?? '—' }}</td><td>{{ $x['status'] }}@if ($x['overdue']) <span class="text-rose-600">· overdue</span>@endif</td><td>{{ $x['examiner'] }}</td><td>{{ $x['moderator'] }}</td><td class="text-right tabular-nums">{{ $x['candidates'] ?? '—' }}</td><td class="text-right tabular-nums">{{ $x['pass_rate'] ?? '—' }}</td><td class="text-right">{{ $x['adjust'] ? 'Yes' : 'No' }}</td></tr>@empty<tr><td colspan="9" class="py-4 text-[var(--ink-3)]">No assessments in this term.</td></tr>@endforelse</tbody></table></div>
    @empty
    @endforelse
  </section>
</div>
</x-app-layout>
