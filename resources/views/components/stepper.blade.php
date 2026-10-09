@props(['current', 'hasExternal'])
<ol class="flex items-start gap-1 overflow-x-auto pb-1" aria-label="Moderation progress">
  @foreach (config('dems.gates') as $i => $g)
    @php
      $skipped = $g['key'] === 'gate3' && ! $hasExternal;
      $done = $i < $current || $current > count(config('dems.gates')) - 1;
      $active = $i === $current;
    @endphp
    <li class="flex min-w-[112px] flex-1 flex-col {{ $skipped ? 'opacity-40' : '' }}">
      <div class="flex items-center">
        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-semibold ring-4 transition
          {{ $done && ! $skipped ? 'bg-emerald-500 text-white ring-emerald-500/15' : ($active ? 'scale-110 bg-accent text-white ring-accent/20 dark:bg-accent-dark dark:text-black' : 'bg-slate-200 text-slate-500 ring-transparent dark:bg-white/10 dark:text-zinc-400') }}">
          @if ($done && ! $skipped)<x-icon name="check" class="h-3.5 w-3.5" />@else{{ $i + 1 }}@endif
        </span>
        @if (! $loop->last)<span class="mx-1.5 h-0.5 flex-1 rounded-full {{ $done ? 'bg-emerald-500/60' : 'bg-slate-200 dark:bg-white/10' }}"></span>@endif
      </div>
      <p class="mt-2 text-[13px] font-semibold leading-tight {{ $active ? 'text-slate-900 dark:text-white' : 'text-slate-600 dark:text-zinc-300' }}">{{ $g['title'] }}</p>
      <p class="text-xs text-slate-400">{{ $skipped ? 'Not assigned' : $g['sub'] }}</p>
    </li>
  @endforeach
</ol>
