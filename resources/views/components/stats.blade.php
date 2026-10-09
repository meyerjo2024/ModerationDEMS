{{-- KPI tiles + decile distribution. $s = ['candidates','passed','pass_rate','average','highest','lowest','invalid'] ; $distribution = int[10]|null --}}
@props(['s', 'distribution' => null])
@php
$pct = fn ($n) => $n === null ? '—' : number_format((float) $n, 1).'%';
$tiles = [['Candidates', $s['candidates'] ?? '—', null], ['Pass rate', $pct($s['pass_rate']), isset($s['passed']) ? $s['passed'].' passed (≥ 50%)' : null], ['Class average', $pct($s['average']), null], ['Highest', $pct($s['highest']), null], ['Lowest', $pct($s['lowest']), null]];
$max = max(1, ...($distribution ?? [0]));
@endphp
<div class="space-y-4">
  <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
    @foreach ($tiles as [$k, $v, $sub])
      <div class="card-soft p-4"><p class="kicker">{{ $k }}</p><p class="mt-1.5 text-2xl font-semibold tabular-nums">{{ $v }}</p>@if ($sub)<p class="mt-0.5 text-xs text-slate-500 dark:text-zinc-400">{{ $sub }}</p>@endif</div>
    @endforeach
  </div>
  @if ($distribution)
    <div class="card-soft p-4">
      <p class="kicker mb-3">Mark distribution</p>
      <div class="flex h-28 items-end gap-1.5" role="img" aria-label="Number of candidates per 10% band">
        @foreach ($distribution as $i => $n)
          <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
            <span class="text-[11px] tabular-nums text-slate-500">{{ $n ?: '' }}</span>
            <div class="w-full rounded-t-md {{ $i >= 5 ? 'bg-accent/80 dark:bg-accent-dark/80' : 'bg-slate-300 dark:bg-zinc-600' }}" style="height: {{ max($n / $max * 100, $n ? 6 : 1.5) }}%"></div>
          </div>
        @endforeach
      </div>
      <div class="mt-1.5 flex gap-1.5 text-[10px] text-slate-400">@foreach ($distribution as $i => $_)<span class="flex-1 text-center">{{ $i * 10 }}</span>@endforeach</div>
    </div>
  @endif
  @if (! empty($s['invalid']))
    <p class="text-xs text-amber-700 dark:text-amber-300">{{ $s['invalid'] }} non-numeric or out-of-range {{ $s['invalid'] === 1 ? 'entry was' : 'entries were' }} excluded (e.g. “ABS”).</p>
  @endif
</div>
