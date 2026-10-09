@php $sigs = $a->signatures->groupBy(fn ($s) => $s->section->value)->map->last()->values(); $logs = $a->auditLogs->where('action', '!=', 'FILE_VIEWED'); @endphp
<section class="card p-6"><p class="kicker mb-3">Key dates</p>
  <dl class="space-y-2.5 text-sm">
    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-zinc-400">Assessment</dt><dd class="font-medium">{{ $a->assessment_date ? \Carbon\Carbon::parse($a->assessment_date)->format('j M Y') : 'Not set' }}</dd></div>
    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-zinc-400">Pre-moderation by</dt><dd class="font-medium">{{ $a->preDue()?->format('j M Y') ?? '—' }}</dd></div>
    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-zinc-400">Post-moderation by</dt><dd class="font-medium">{{ $a->postDue()?->format('j M Y') ?? '—' }}</dd></div>
  </dl></section>
<section class="card p-6"><p class="kicker mb-3">People</p>
  <dl class="space-y-2.5 text-sm">
    @foreach ([['Examiner', $a->examiner->name], ['Internal moderator', $a->internalModerator->name], ['External moderator', $a->externalModerator?->name], ['Head of Department', $a->subject->hod->name]] as [$k, $v])
      <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-zinc-400">{{ $k }}</dt><dd class="{{ $v ? 'text-right font-medium' : 'text-slate-400' }}">{{ $v ?? 'Not assigned' }}</dd></div>
    @endforeach
  </dl></section>
@if ($sigs->isNotEmpty())
  <section class="card p-6"><p class="kicker mb-3 flex items-center gap-1.5"><x-icon name="shield" class="h-3.5 w-3.5 text-emerald-500" /> Signatures</p>
    <ul class="space-y-3">@foreach ($sigs as $s)
      <li class="card-soft p-3"><p class="text-[11px] font-semibold uppercase tracking-wider text-accent dark:text-accent-dark">{{ $s->section->label() }}</p>
        <div class="my-1.5 rounded-lg bg-white p-1.5"><img src="{{ $s->image_data }}" alt="Signature of {{ $s->user->name }}" class="h-12 w-auto max-w-full object-contain"></div>
        <p class="text-sm font-semibold">{{ $s->user->name }}</p><p class="text-xs text-slate-500 dark:text-zinc-400">{{ $s->signed_at->utc()->format('j M Y, H:i') }} UTC</p></li>
    @endforeach</ul></section>
@endif
<section class="card p-6"><p class="kicker mb-4">Audit trail</p>
  <ol class="relative space-y-4 border-l border-slate-200 pl-5 dark:border-white/10">
    @foreach ($logs as $l)
      <li class="relative"><span class="absolute -left-[26px] top-1.5 h-2.5 w-2.5 rounded-full bg-accent dark:bg-accent-dark"></span>
        <p class="text-sm font-medium">{{ config('dems.audit_labels')[$l->action] ?? $l->action }}</p>
        @if (! empty($l->details['filename']))<p class="truncate text-xs text-slate-500">{{ $l->details['filename'] }}</p>@endif
        <p class="text-xs text-slate-400">{{ $l->user?->name ?? 'System' }} · {{ $l->created_at->utc()->format('j M Y, H:i') }} UTC</p></li>
    @endforeach
  </ol>
  <p class="mt-4 text-[11px] leading-snug text-slate-400">Entries are hash-chained: each one cryptographically covers the one before it.</p></section>
