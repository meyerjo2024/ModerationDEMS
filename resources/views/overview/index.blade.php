<x-app-layout title="Overview" wide>
@php
  $qs = fn (array $over = []) => route('overview', array_filter(array_merge(request()->query(), $over), fn ($v) => $v !== '' && $v !== null && $v !== 0));
@endphp
<div class="space-y-8">
  <div><p class="kicker">{{ auth()->user()->hasRole(\App\Enums\Role::Hod) ? 'Head of Department' : 'Head of Programme' }}</p><h1 class="mt-1 text-3xl font-semibold">Moderation overview</h1>
    @if (auth()->user()->hasRole(\App\Enums\Role::Hod))<p class="mt-2"><a href="{{ route('dean-report') }}" class="btn-secondary !py-1.5 text-sm">Report for the Dean →</a></p>@endif
    <p class="mt-1 text-sm text-[var(--ink-2)]"><b>Pending</b> = waiting on a moderator or signatory · <b>In process</b> = with the examiner · <b>Overdue</b> = past its pre- or post-moderation deadline (also counted in pending or in process).</p></div>

  <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
    <div class="card p-5"><p class="kicker">Subjects</p><p class="mt-2 text-4xl font-semibold tabular-nums">{{ $totals['subjects'] }}</p></div>
    <a href="{{ $qs(['coverage' => 'no_moderator']) }}" class="card p-5 transition hover:-translate-y-0.5 hover:shadow-lift"><p class="kicker">Without an internal moderator</p><p class="mt-2 text-4xl font-semibold tabular-nums {{ $totals['no_mod'] ? 'text-rose-600' : '' }}">{{ $totals['no_mod'] }}</p></a>
    <a href="{{ $qs(['coverage' => 'no_examiner']) }}" class="card p-5 transition hover:-translate-y-0.5 hover:shadow-lift"><p class="kicker">Without an examiner</p><p class="mt-2 text-4xl font-semibold tabular-nums {{ $totals['no_exam'] ? 'text-amber-600' : '' }}">{{ $totals['no_exam'] }}</p></a>
  </div>

  <section class="card p-6"><p class="kicker mb-3">By qualification</p>
    <div class="overflow-x-auto"><table class="w-full min-w-[40rem] text-sm">
      <thead class="text-left text-xs text-[var(--ink-3)]"><tr><th class="py-2 pr-3">Qualification</th><th class="px-2 text-right">Subjects</th><th class="px-2 text-right">With moderator</th><th class="px-2 text-right">No moderator</th><th class="px-2 text-right">Pending</th><th class="px-2 text-right">In process</th><th class="px-2 text-right">Completed</th><th class="px-2 text-right">Overdue</th></tr></thead>
      <tbody class="divide-y divide-[var(--divider)]">@forelse ($quals as $q)
        <tr class="{{ $f['qualification'] === $q['name'] ? 'bg-accent/5' : '' }}"><td class="py-2.5 pr-3"><a href="{{ $qs(['qualification' => $f['qualification'] === $q['name'] ? '' : $q['name']]) }}#subjects" class="font-semibold text-accent hover:underline">{{ $q['name'] }}</a></td>
          <td class="px-2 text-right tabular-nums">{{ $q['subjects'] }}</td><td class="px-2 text-right tabular-nums">{{ $q['with_mod'] }}</td><td class="px-2 text-right tabular-nums {{ $q['no_mod'] ? 'font-semibold text-rose-600' : '' }}">{{ $q['no_mod'] }}</td>
          <td class="px-2 text-right tabular-nums">{{ $q['pending'] }}</td><td class="px-2 text-right tabular-nums">{{ $q['process'] }}</td><td class="px-2 text-right tabular-nums text-emerald-600">{{ $q['done'] }}</td><td class="px-2 text-right tabular-nums {{ $q['overdue'] ? 'font-semibold text-rose-600' : '' }}">{{ $q['overdue'] }}</td></tr>
      @empty<tr><td colspan="8" class="py-6 text-[var(--ink-3)]">No subjects are assigned to you yet. Import the subject list under Admin.</td></tr>@endforelse</tbody></table></div></section>

  <form method="GET" action="{{ route('overview') }}" class="card grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
    <p class="kicker sm:col-span-2 lg:col-span-4">Filter subjects and tests</p>
    <div><label class="label">Qualification</label><select name="qualification" class="field"><option value="">All</option>@foreach ($quals as $q)<option value="{{ $q['name'] }}" @selected($f['qualification'] === $q['name'])>{{ $q['name'] }}</option>@endforeach</select></div>
    <div><label class="label">Staff member</label><select name="staff" class="field"><option value="">Anyone</option>@foreach ($staff as $s)<option value="{{ $s->id }}" @selected($f['staff'] === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <div><label class="label">…as</label><select name="staff_role" class="field"><option value="">Examiner or moderator</option><option value="EXAMINER" @selected($f['staff_role'] === 'EXAMINER')>Examiner</option><option value="INTERNAL_MODERATOR" @selected($f['staff_role'] === 'INTERNAL_MODERATOR')>Internal moderator</option></select></div>
    <div><label class="label">Coverage</label><select name="coverage" class="field"><option value="">All subjects</option><option value="no_moderator" @selected($f['coverage'] === 'no_moderator')>No internal moderator</option><option value="no_examiner" @selected($f['coverage'] === 'no_examiner')>No examiner</option><option value="covered" @selected($f['coverage'] === 'covered')>Examiner and moderator attached</option></select></div>
    <div><label class="label">Tests from</label><input type="date" name="from" value="{{ $from }}" class="field"></div>
    <div><label class="label">Tests to</label><input type="date" name="to" value="{{ $to }}" class="field"></div>
    <div><label class="label">Search subject</label><input name="q" value="{{ $f['q'] }}" class="field" placeholder="Code or name"></div>
    <div class="flex items-end gap-3"><button class="btn-primary">Apply</button><a href="{{ route('overview') }}" class="text-sm font-semibold text-accent">Clear</a></div>
  </form>

  <section class="card p-6"><p class="kicker mb-3">Tests {{ \Carbon\Carbon::parse($from)->format('j M Y') }} – {{ \Carbon\Carbon::parse($to)->format('j M Y') }} · {{ $tests->count() }}</p>
    <ul class="divide-y divide-[var(--divider)] text-sm">@forelse ($tests as $t)
      <li class="flex flex-wrap items-center justify-between gap-2 py-2.5"><span><span class="font-semibold">{{ $t->subject->code }} · {{ $t->label }}</span> <span class="text-[var(--ink-2)]">{{ $t->subject->name }}</span>@if ($t->who) <span class="text-[var(--ink-3)]">· {{ $t->who }}</span>@endif</span>
        <span class="flex items-center gap-3"><span class="text-xs text-[var(--ink-3)]">{{ $t->status }}</span><span class="rounded-full bg-[var(--fog)] px-2.5 py-1 text-[11px] font-semibold">{{ $t->date->format('j M Y') }}</span>@if ($t->url)<a href="{{ $t->url }}" class="text-xs font-semibold text-accent">Open →</a>@endif</span></li>
    @empty<li class="py-4 text-[var(--ink-3)]">No tests in this period for the subjects shown.</li>@endforelse</ul></section>

  <section id="subjects" class="card p-6"><p class="kicker mb-3">Subjects · {{ $rows->count() }}@if ($f['qualification']) · {{ $f['qualification'] }}@endif</p>
    <div class="overflow-x-auto"><table class="w-full min-w-[52rem] text-sm">
      <thead class="text-left text-xs text-[var(--ink-3)]"><tr><th class="py-2 pr-3">Subject</th><th class="px-2">Examiner(s)</th><th class="px-2">Internal moderator(s)</th><th class="px-2 text-right">Pending</th><th class="px-2 text-right">In process</th><th class="px-2 text-right">Done</th><th class="px-2 text-right">Overdue</th><th class="px-2">Next test</th></tr></thead>
      <tbody class="divide-y divide-[var(--divider)]">@forelse ($rows as $r)
        <tr><td class="py-2.5 pr-3"><a href="{{ route('overview.subject', $r->subject) }}" class="font-semibold text-accent hover:underline">{{ $r->subject->code }}</a> <span class="text-[var(--ink-2)]">{{ $r->subject->name }}</span></td>
          <td class="px-2">@forelse ($r->examiners as $p)<a href="{{ $qs(['staff' => $p['id'], 'staff_role' => 'EXAMINER']) }}" class="block hover:underline">{{ $p['name'] }}</a>@empty<span class="rounded-full bg-amber-500/10 px-2 py-0.5 text-[11px] font-semibold text-amber-700">none</span>@endforelse</td>
          <td class="px-2">@forelse ($r->moderators as $p)<a href="{{ $qs(['staff' => $p['id'], 'staff_role' => 'INTERNAL_MODERATOR']) }}" class="block hover:underline">{{ $p['name'] }}</a>@empty<span class="rounded-full bg-rose-500/10 px-2 py-0.5 text-[11px] font-semibold text-rose-700">none</span>@endforelse</td>
          <td class="px-2 text-right tabular-nums">{{ $r->pending }}</td><td class="px-2 text-right tabular-nums">{{ $r->process }}</td><td class="px-2 text-right tabular-nums text-emerald-600">{{ $r->done }}</td><td class="px-2 text-right tabular-nums {{ $r->overdue ? 'font-semibold text-rose-600' : '' }}">{{ $r->overdue }}</td>
          <td class="px-2 text-[var(--ink-2)]">{{ $r->next?->format('j M Y') ?? '—' }}</td></tr>
      @empty<tr><td colspan="8" class="py-6 text-[var(--ink-3)]">No subjects match these filters.</td></tr>@endforelse</tbody></table></div></section>
</div>
</x-app-layout>
