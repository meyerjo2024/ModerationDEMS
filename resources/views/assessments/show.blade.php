<x-app-layout :title="$a->title()" wide>
@php $S = \App\Enums\AssessmentStatus::class;

$isExaminer = $a->examiner_id === $user->id;
$isInternal = $a->internal_moderator_id === $user->id;
$isExternal = $a->external_moderator_id === $user->id;
$st = $a->status;
$panel = match (true) {
  in_array($st, [$S::Draft, $S::RevisionRequested], true) && $isExaminer => 'section1',
  $st === $S::PendingPreModeration && $isInternal => 'pre-review',
  $st === $S::ReadyForPostModeration && $isExaminer => 'section2',
  $st === $S::PendingFinalModeration && $isInternal => 'final-review',
  $st === $S::PendingExternalModeration && $isExternal => 'final-review',
  $st === $S::PendingSection3Signoff && ($isExaminer || $a->subject->hod_id === $user->id) => 'section3',
  default => 'summary',
};
$stage = $st === $S::PendingExternalModeration ? 'external' : 'internal';
@endphp
<div class="space-y-8">
  <div>
    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-zinc-200"><x-icon name="arrow-left" /> Dashboard</a>
    <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
      <div><h1 class="text-3xl font-semibold sm:text-4xl">{{ $a->title() }}</h1><p class="mt-1 text-slate-500 dark:text-zinc-400">{{ $a->subject->name }}@if ($a->revision > 1) · Revision {{ $a->revision }}@endif</p></div>
      <div class="flex flex-col items-end gap-2"><x-status-badge :status="$st" class="!px-4 !py-1.5 !text-sm" /><x-deadline :a="$a" full /></div>
    </div>
  </div>
  <div class="card p-5 sm:p-6"><x-stepper :current="$st->gateIndex((bool) $a->external_moderator_id)" :has-external="(bool) $a->external_moderator_id" /></div>
  <div x-data="sidePanel" class="space-y-3">
  <div class="hidden justify-end lg:flex"><button type="button" @click="toggle()" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold text-[var(--ink-2)] hover:bg-[var(--fog)]" :aria-expanded="show"><span x-text="show ? 'Hide details panel ›' : '‹ Show details panel'"></span></button></div>
  <div class="grid items-start gap-6" :class="show ? 'lg:grid-cols-[minmax(0,1fr)_340px]' : 'lg:grid-cols-1'">
    <div class="min-w-0">@include('assessments.panels.'.$panel, ['a' => $a, 'user' => $user, 'stage' => $stage])</div>
    <aside x-show="show" class="space-y-6 lg:sticky lg:top-20">@include('assessments.partials.sidebar', ['a' => $a])</aside>
  </div>
  </div>
</div>
</x-app-layout>
