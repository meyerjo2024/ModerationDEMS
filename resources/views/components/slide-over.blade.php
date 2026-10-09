@props(['open', 'title', 'subtitle' => null])
<div x-show="{{ $open }}" x-cloak x-transition.duration.300ms class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="{{ $title }}" @keydown.escape.window="{{ $open }} = false">
  <div x-show="{{ $open }}" x-transition.opacity @click="{{ $open }} = false" class="absolute inset-0 bg-slate-900/20 backdrop-blur-sm dark:bg-black/50"></div>
  <aside x-show="{{ $open }}" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
    x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
    class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col border-l border-slate-200/60 bg-white/85 shadow-lift backdrop-blur-2xl dark:border-white/10 dark:bg-zinc-900/85 sm:inset-y-3 sm:right-3 sm:rounded-3xl sm:border">
    <header class="flex items-start justify-between gap-4 p-6 pb-3">
      <div><h2 class="text-xl font-semibold">{{ $title }}</h2>@if ($subtitle)<p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">{{ $subtitle }}</p>@endif</div>
      <button type="button" @click="{{ $open }} = false" aria-label="Close" class="rounded-full p-2 text-slate-500 hover:bg-slate-900/5 dark:hover:bg-white/10"><x-icon name="x" class="h-5 w-5" /></button>
    </header>
    <div class="flex-1 overflow-y-auto p-6 pt-3">{{ $slot }}</div>
  </aside>
</div>
