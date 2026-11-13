{{-- $docs: list of [label, Attachment|null]. PDFs and Word files open in the browser with comments. Revised uploads become Version 2, 3 …; the version that passed pre-moderation is marked Final. --}}
@props(['docs'])
@php
  $docs = collect($docs)->filter(fn ($d) => $d[1])->values();
  $word = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
  $commentable = [\App\Enums\AttachmentKind::Paper, \App\Enums\AttachmentKind::Memo];
  $stateLabel = ['final' => 'Final', 'current' => 'Latest', 'superseded' => 'Superseded'];
  $stateClass = ['final' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300', 'current' => 'bg-accent/10 text-accent', 'superseded' => 'bg-slate-500/10 text-slate-500'];
@endphp
@if ($docs->isEmpty())
  <p class="text-sm text-slate-500">No documents were uploaded.</p>
@else
  <div x-data="{ tab: 0, ver: {} }" class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div role="tablist" class="inline-flex max-w-full gap-1 overflow-x-auto rounded-full bg-slate-900/5 p-1 dark:bg-white/10">
        @foreach ($docs as $i => [$label, $att])
          @php $vers = $att->assessment->versionsOf($att); $n = $vers->count(); @endphp
          <button type="button" role="tab" @click="tab = {{ $i }}" :aria-selected="tab === {{ $i }}"
            :class="tab === {{ $i }} ? 'bg-white text-slate-900 shadow-sm dark:bg-white/15 dark:text-white' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400'"
            class="whitespace-nowrap rounded-full px-3.5 py-1.5 text-[13px] font-medium transition">{{ $label }}@if ($n > 1) <span class="text-[11px] opacity-60">v{{ $n }}</span>@endif</button>
        @endforeach
      </div>
    </div>
    @foreach ($docs as $i => [$label, $att])
      @php $vers = $att->assessment->versionsOf($att); $last = $vers->count() - 1; @endphp
      <div x-show="tab === {{ $i }}" @if ($i) x-cloak @endif class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex flex-wrap items-center gap-1.5">
            @foreach ($vers as $j => $v)
              @php $st = $att->assessment->versionState($v); @endphp
              @if ($vers->count() > 1)
                <button type="button" @click="ver[{{ $i }}] = {{ $j }}" :class="(ver[{{ $i }}] ?? {{ $last }}) === {{ $j }} ? 'ring-2 ring-accent/50' : ''" class="inline-flex items-center gap-1.5 rounded-full bg-[var(--fog)] px-3 py-1 text-xs font-semibold">
                  Version {{ $j + 1 }} <span class="text-[var(--ink-3)] font-normal">{{ $v->created_at->utc()->format('j M') }}</span> <span class="rounded-full px-1.5 py-0.5 text-[10px] {{ $stateClass[$st] }}">{{ $stateLabel[$st] }}</span></button>
              @elseif ($st === 'final')
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $stateClass[$st] }}">Final</span>
              @endif
            @endforeach
          </div>
          @foreach ($vers as $j => $v)
            <a x-show="(ver[{{ $i }}] ?? {{ $last }}) === {{ $j }}" href="{{ route('files.show', $v) }}" class="btn-secondary !py-1.5 text-[13px]"><x-icon name="download" /> Download{{ $vers->count() > 1 ? ' version '.($j + 1) : '' }}</a>
          @endforeach
        </div>
        @foreach ($vers as $j => $v)
          <template x-if="tab === {{ $i }} && (ver[{{ $i }}] ?? {{ $last }}) === {{ $j }}"><div>
            @if ($v->isPdf() && in_array($v->kind, $commentable, true))
              <x-pdf-review :att="$v" />
            @elseif ($v->isPdf())
              <iframe title="{{ $label }}" src="{{ route('files.show', $v) }}?inline=1#view=FitH" class="h-[70vh] w-full rounded-2xl border border-slate-200/70 bg-white dark:border-white/10"></iframe>
            @elseif ($v->mime_type === $word && in_array($v->kind, $commentable, true))
              <x-word-review :att="$v" />
            @else
              <div class="card-soft grid place-items-center gap-3 px-6 py-14 text-center">
                <x-icon name="file" class="h-9 w-9 text-slate-400" />
                <div><p class="font-medium">{{ $v->filename }}</p><p class="text-sm text-slate-500 dark:text-zinc-400">{{ number_format($v->size / 1024) }} KB · Older .doc files can’t be previewed — save as .docx or PDF.</p></div>
                <a href="{{ route('files.show', $v) }}" class="btn-primary"><x-icon name="download" /> Download to review</a>
              </div>
            @endif
          </div></template>
        @endforeach
      </div>
    @endforeach
  </div>
@endif
