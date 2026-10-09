{{-- $docs: list of [label, Attachment|null]. PDFs preview inline; Word files are download-only. --}}
@props(['docs'])
@php $docs = collect($docs)->filter(fn ($d) => $d[1])->values(); @endphp
@if ($docs->isEmpty())
  <p class="text-sm text-slate-500">No documents were uploaded.</p>
@else
  <div x-data="{ tab: 0 }" class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div role="tablist" class="inline-flex max-w-full gap-1 overflow-x-auto rounded-full bg-slate-900/5 p-1 dark:bg-white/10">
        @foreach ($docs as $i => [$label, $att])
          <button type="button" role="tab" @click="tab = {{ $i }}" :aria-selected="tab === {{ $i }}"
            :class="tab === {{ $i }} ? 'bg-white text-slate-900 shadow-sm dark:bg-white/15 dark:text-white' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400'"
            class="whitespace-nowrap rounded-full px-3.5 py-1.5 text-[13px] font-medium transition">{{ $label }}</button>
        @endforeach
      </div>
      @foreach ($docs as $i => [$label, $att])
        <a x-show="tab === {{ $i }}" href="{{ route('files.show', $att) }}" class="btn-secondary !py-1.5 text-[13px]"><x-icon name="download" /> Download</a>
      @endforeach
    </div>
    @foreach ($docs as $i => [$label, $att])
      <div x-show="tab === {{ $i }}" @if ($i) x-cloak @endif>
        @if ($att->isPdf())
          <iframe title="{{ $label }}" x-bind:src="tab === {{ $i }} ? '{{ route('files.show', $att) }}?inline=1#view=FitH' : null" class="h-[70vh] w-full rounded-2xl border border-slate-200/70 bg-white dark:border-white/10"></iframe>
        @else
          <div class="card-soft grid place-items-center gap-3 px-6 py-14 text-center">
            <x-icon name="file" class="h-9 w-9 text-slate-400" />
            <div><p class="font-medium">{{ $att->filename }}</p><p class="text-sm text-slate-500 dark:text-zinc-400">{{ number_format($att->size / 1024) }} KB · Word documents can’t be previewed in the browser.</p></div>
            <a href="{{ route('files.show', $att) }}" class="btn-primary"><x-icon name="download" /> Download to review</a>
          </div>
        @endif
      </div>
    @endforeach
  </div>
@endif
