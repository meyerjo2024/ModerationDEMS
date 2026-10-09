@props(['a', 'full' => false])
@php $d = $a->deadline(); @endphp
@if ($d)
  @php
    $tone = ['overdue' => 'bg-rose-500/10 text-rose-700 dark:text-rose-300', 'soon' => 'bg-amber-500/10 text-amber-700 dark:text-amber-300', 'ok' => 'bg-slate-900/5 text-slate-600 dark:bg-white/10 dark:text-zinc-300'][$d['state']];
    $when = $d['days'] < 0 ? abs($d['days']).' day'.(abs($d['days']) === 1 ? '' : 's').' overdue' : ($d['days'] === 0 ? 'due today' : 'in '.$d['days'].' day'.($d['days'] === 1 ? '' : 's'));
  @endphp
  <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold '.$tone]) }} title="Assessment date: {{ \Carbon\Carbon::parse($a->assessment_date)->format('j M Y') }}">
    <x-icon name="hourglass" class="h-3 w-3" /> {{ $d['phase'] }} {{ $full ? 'due '.$d['due']->format('j M Y').' · ' : '· ' }}{{ $when }}
  </span>
@endif
