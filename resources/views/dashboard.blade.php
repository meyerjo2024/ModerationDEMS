<x-app-layout title="Dashboard" wide>
@php $S = \App\Enums\AssessmentStatus::class;

$counts = $items->countBy(fn ($i) => $i->status->value);
$actionCount = $items->filter(fn ($i) => $i->needsActionFrom($user))->count();
$first = collect(explode(' ', $user->name))->reject(fn ($p) => preg_match('/^(dr|prof|mr|mrs|ms)\.?$/i', $p))->first() ?? $user->name;
$meta = $items->map(fn ($i) => ['status' => $i->status->value, 'action' => $i->needsActionFrom($user), 'text' => "{$i->subject->code} {$i->subject->name} {$i->number}"])->values();
$headline = [$S::PendingPreModeration, $S::RevisionRequested, $S::ReadyForPostModeration, $S::Completed];
$tabs = ['ALL' => ['All', $items->count()], 'ACTION' => ['Needs my action', $actionCount], 'DRAFT' => ['Drafts', $counts['DRAFT'] ?? 0], 'FINAL' => ['Final review', ($counts['PENDING_FINAL_MODERATION'] ?? 0) + ($counts['PENDING_EXTERNAL_MODERATION'] ?? 0) + ($counts['PENDING_SECTION3_SIGNOFF'] ?? 0)]];
@endphp
<div x-data="dashboard(@js($meta))" class="space-y-8">
  <div class="hero-plate relative overflow-hidden bg-navy p-8 text-white sm:p-10">
    <div class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-brand/30 blur-3xl"></div>
    <div class="pointer-events-none absolute inset-0 opacity-[.06]" style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:22px 22px"></div>
    <div class="relative flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[11px] font-semibold uppercase tracking-[.16em] text-brand">Overview</p>
        <h1 class="mt-1 text-3xl font-semibold sm:text-4xl">Welcome back, {{ $first }}.</h1>
        <p class="mt-1 text-white/70">{{ $actionCount ? $actionCount.' '.\Illuminate\Support\Str::plural('record', $actionCount).' waiting on you.' : 'Nothing is waiting on you right now.' }}</p>
      </div>
      @if ($user->hasRole(\App\Enums\Role::Examiner))<a href="{{ route('assessments.create') }}" class="btn-brand"><x-icon name="file-plus" /> New assessment</a>@endif
    </div>
  </div>

  <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach ($headline as $s)
      @php $t = $s->tone(); @endphp
      <button type="button" @click="filter = filter === '{{ $s->value }}' ? 'ALL' : '{{ $s->value }}'" :class="filter === '{{ $s->value }}' && 'ring-2 {{ $t['ring'] }}'"
        class="card relative overflow-hidden p-5 text-left transition hover:-translate-y-0.5 hover:shadow-lift">
        <span class="pointer-events-none absolute inset-0 bg-gradient-to-br {{ $t['wash'] }} to-transparent"></span>
        <span class="relative flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-zinc-300"><span class="h-2 w-2 rounded-full {{ $t['dot'] }} {{ $s !== $S::Completed ? 'animate-pulse-ring' : '' }}"></span>{{ $s->label() }}</span>
        <span class="relative mt-3 block text-4xl font-semibold tabular-nums">{{ $counts[$s->value] ?? 0 }}</span>
      </button>
    @endforeach
  </div>

  <div class="flex flex-wrap items-center justify-between gap-3">
    <div role="tablist" class="inline-flex max-w-full gap-1 overflow-x-auto rounded-full bg-slate-900/5 p-1 dark:bg-white/10">
      @foreach ($tabs as $key => [$label, $n])
        <button type="button" role="tab" @click="filter = '{{ $key }}'" :aria-selected="filter === '{{ $key }}'"
          :class="filter === '{{ $key }}' ? 'bg-white text-slate-900 shadow-sm dark:bg-white/15 dark:text-white' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400'"
          class="whitespace-nowrap rounded-full px-3.5 py-1.5 text-[13px] font-medium transition">{{ $label }} <span class="ml-1 text-xs tabular-nums opacity-60">{{ $n }}</span></button>
      @endforeach
    </div>
    <div class="relative w-full sm:w-64">
      <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
      <input x-model="q" placeholder="Search subject or number" aria-label="Search" class="field !rounded-full pl-10">
    </div>
  </div>

  @if ($items->isEmpty())
    <div class="card grid place-items-center gap-3 px-6 py-16 text-center">
      <span class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/10 text-accent dark:text-accent-dark"><x-icon name="sparkles" class="h-6 w-6" /></span>
      <p class="text-lg font-semibold">No assessments yet</p>
      <p class="max-w-sm text-sm text-slate-500 dark:text-zinc-400">{{ $user->hasRole(\App\Enums\Role::Examiner) ? 'Start your first assessment to begin the moderation workflow.' : 'Records appear here once an examiner assigns them to you.' }}</p>
    </div>
  @else
    <div x-show="none" x-cloak class="card px-6 py-14 text-center"><p class="text-lg font-semibold">No matching records</p><p class="text-sm text-slate-500">Try a different filter or search term.</p></div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      @foreach ($items as $i => $a)
        @php $t = $a->status->tone(); $step = $a->status->gateIndex((bool) $a->external_moderator_id); $mine = $a->needsActionFrom($user); @endphp
        <a href="{{ route('assessments.show', $a) }}" x-show="show({{ $i }})" x-transition.opacity
          class="card group relative block overflow-hidden p-6 transition hover:-translate-y-1 hover:shadow-lift {{ $mine ? 'ring-2 '.$t['ring'] : '' }}">
          <span class="pointer-events-none absolute inset-x-0 top-0 h-28 bg-gradient-to-b {{ $t['wash'] }} to-transparent"></span>
          <div class="relative flex items-start justify-between gap-3"><x-status-badge :status="$a->status" /><x-icon name="arrow-up-right" class="h-5 w-5 text-slate-300 transition group-hover:text-accent" /></div>
          <div class="relative mt-5"><p class="text-3xl font-semibold tracking-tight">{{ $a->subject->code }}</p><p class="mt-0.5 text-sm font-medium text-slate-700 dark:text-zinc-200">{{ $a->number }}</p><p class="truncate text-sm text-slate-500 dark:text-zinc-400">{{ $a->subject->name }}</p></div>
          <div class="relative mt-3"><x-deadline :a="$a" /></div>
          @if ($a->pass_rate !== null)
            <div class="relative mt-4 flex gap-6 text-sm"><span><span class="kicker block">Pass rate</span><span class="font-semibold tabular-nums">{{ number_format($a->pass_rate, 1) }}%</span></span><span><span class="kicker block">Candidates</span><span class="font-semibold tabular-nums">{{ $a->candidate_count }}</span></span></div>
          @endif
          <div class="relative mt-5 flex gap-1" aria-hidden="true">
            @foreach (config('dems.gates') as $g => $_)<span class="h-1 flex-1 rounded-full {{ $g < $step ? 'bg-emerald-500/70' : ($g === $step ? explode(' ', $t['dot'])[0] : 'bg-slate-200 dark:bg-white/10') }}"></span>@endforeach
          </div>
          <div class="relative mt-4 flex items-center justify-between text-xs text-slate-500 dark:text-zinc-400"><span class="truncate">{{ $a->examiner->name }}</span><span class="shrink-0">@if ($mine)<span class="font-semibold text-accent dark:text-accent-dark">Your turn</span>@else{{ $a->updated_at->utc()->format('j M Y') }}@endif</span></div>
        </a>
      @endforeach
    </div>
  @endif
</div>
</x-app-layout>
