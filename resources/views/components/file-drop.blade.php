{{-- $onFile is an Alpine expression run with `file` in scope, e.g. "upload('PAPER', file)" --}}
@props(['label', 'hint', 'accept', 'onFile', 'busy' => 'false'])
<div x-data="{ over: false }" class="w-full">
  <button type="button" :disabled="{{ $busy }}" @click="$refs.input.click()"
    @dragover.prevent="over = true" @dragleave="over = false"
    @drop.prevent="over = false; ((file) => { if (file) { {!! $onFile !!} } })($event.dataTransfer.files[0])"
    :class="over ? 'border-accent bg-accent/5' : 'border-slate-300/80 hover:border-accent/60 hover:bg-slate-50 dark:border-white/15 dark:hover:bg-white/5'"
    class="flex w-full items-center gap-3 rounded-2xl border border-dashed px-4 py-3.5 text-left transition disabled:opacity-60">
    <span class="grid h-10 w-10 place-items-center rounded-xl bg-accent/10 text-accent dark:text-accent-dark">
      <x-icon name="spinner" x-show="{{ $busy }}" class="h-5 w-5 animate-spin" /><x-icon name="upload" x-show="!({{ $busy }})" class="h-5 w-5" />
    </span>
    <span class="min-w-0"><span class="block text-sm font-medium">{{ $label }}</span><span class="block truncate text-xs text-slate-500 dark:text-zinc-400">{{ $hint }}</span></span>
  </button>
  <input x-ref="input" type="file" accept="{{ $accept }}" hidden @change="((file) => { if (file) { {!! $onFile !!} } })($event.target.files[0]); $event.target.value = ''">
</div>
