<x-app-layout title="{{ $subject->code }}">
<div class="space-y-8">
  <div><a href="{{ route('overview') }}" class="text-sm font-semibold text-accent">← Overview</a>
    <h1 class="mt-2 text-3xl font-semibold">{{ $subject->code }} <span class="font-normal text-[var(--ink-2)]">{{ $subject->name }}</span></h1>
    @if ($subject->qualification)<p class="text-sm text-[var(--ink-3)]">{{ $subject->qualification }}</p>@endif</div>
  <div class="grid gap-6 md:grid-cols-2">
    <section class="card p-6"><p class="kicker mb-3">Examiners</p><ul class="space-y-1.5 text-sm">@forelse ($examiners as $e)<li>{{ $e['name'] }}@if ($e['default']) <span class="text-[var(--ink-3)]">· default moderator {{ $e['default'] }}</span>@endif</li>@empty<li class="text-amber-700">No examiner has chosen this subject.</li>@endforelse</ul></section>
    <section class="card p-6"><p class="kicker mb-3">Internal moderators</p><ul class="space-y-1.5 text-sm">@forelse ($moderators as $m)<li>{{ $m }}</li>@empty<li class="text-rose-700">No internal moderator is attached.</li>@endforelse</ul></section>
  </div>
  <section class="card p-6"><p class="kicker mb-3">Assessments · {{ $assessments->count() }}</p>
    <ul class="divide-y divide-[var(--divider)] text-sm">@forelse ($assessments as $a)
      <li class="flex flex-wrap items-center justify-between gap-3 py-3"><span><span class="font-semibold">{{ $a->number_label }}@if ($a->label !== '') ({{ $a->label }})@endif</span> <span class="text-[var(--ink-3)]">· {{ $a->examiner->name }} / {{ $a->internalModerator?->name ?? 'no moderator' }}@if ($a->assessment_date) · written {{ $a->assessment_date->format('j M Y') }}@endif</span></span>
        <span class="flex items-center gap-3"><x-deadline :a="$a" /><x-status-badge :status="$a->status" /><a href="{{ route('assessments.show', $a) }}" class="text-xs font-semibold text-accent">Open →</a></span></li>
    @empty<li class="py-4 text-[var(--ink-3)]">Nothing has been started for this subject yet.</li>@endforelse</ul></section>
  @if ($plans->isNotEmpty())<section class="card p-6"><p class="kicker mb-3">Planned, not started yet</p>
    <ul class="divide-y divide-[var(--divider)] text-sm">@foreach ($plans as $p)<li class="flex justify-between gap-3 py-2.5"><span><span class="font-semibold">{{ $p->number_label }}</span> <span class="text-[var(--ink-3)]">· {{ $p->user?->name }}</span></span><span>{{ $p->assessment_date->format('j M Y') }}</span></li>@endforeach</ul></section>@endif
</div>
</x-app-layout>
