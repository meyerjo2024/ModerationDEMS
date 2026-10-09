@props(['name', 'meta' => null, 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'flex items-center gap-2 rounded-xl bg-slate-900/[.04] px-3 py-2 text-sm dark:bg-white/5'.($href ? ' hover:bg-slate-900/[.07] dark:hover:bg-white/10' : '')]) }}>
  <x-icon name="file" class="h-4 w-4 shrink-0 text-slate-400" /><span class="truncate font-medium">{{ $name }}</span>@if ($meta)<span class="shrink-0 text-xs text-slate-400">{{ $meta }}</span>@endif
</{{ $tag }}>
