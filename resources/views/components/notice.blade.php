@props(['tone' => 'info', 'title' => null])
@php
$map = [
  'error' => ['bg-rose-500/10 text-rose-700 dark:text-rose-300', 'alert'], 'success' => ['bg-emerald-500/10 text-emerald-700 dark:text-emerald-300', 'check-circle'],
  'info' => ['bg-sky-500/10 text-sky-700 dark:text-sky-300', 'info'], 'warn' => ['bg-amber-500/10 text-amber-800 dark:text-amber-300', 'alert'],
];
[$cls, $icon] = $map[$tone];
@endphp
<div role="{{ $tone === 'error' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => "flex gap-3 rounded-2xl px-4 py-3 text-sm {$cls}"]) }}>
  <x-icon :name="$icon" class="mt-0.5 h-4 w-4 shrink-0" />
  <div>@if ($title)<p class="font-semibold">{{ $title }}</p>@endif<div class="{{ $title ? 'mt-0.5 opacity-90' : '' }}">{{ $slot }}</div></div>
</div>
