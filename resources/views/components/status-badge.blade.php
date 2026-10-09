@props(['status'])
@php $t = $status->tone(); $live = ! in_array($status->value, ['COMPLETED', 'DRAFT'], true); @endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {$t['chip']}"]) }}>
  <span class="h-1.5 w-1.5 rounded-full {{ $t['dot'] }} {{ $live ? 'animate-pulse-ring' : '' }}"></span>{{ $status->label() }}
</span>
