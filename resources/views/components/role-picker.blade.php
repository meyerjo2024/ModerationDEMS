{{-- Role checkboxes; ticking Head of Programme reveals the qualifications that person heads. --}}
@props(['assignable', 'selected' => [], 'programmes' => [], 'quals' => [], 'note' => null])
@php $hop = \App\Enums\Role::HeadOfProgramme->value; @endphp
<div x-data="{ hop: @js(in_array($hop, $selected, true)) }" class="space-y-2">
  <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 text-sm">
    @foreach ($assignable as $r)
      <label class="inline-flex items-center gap-2"><input type="checkbox" name="roles[]" value="{{ $r->value }}" class="h-4 w-4 rounded accent-[#006699]" @checked(in_array($r->value, $selected, true)) @if ($r->value === $hop) x-model="hop" @endif> {{ $r->label() }}</label>
    @endforeach
  </div>
  <div x-show="hop" x-cloak class="card-soft space-y-1.5 p-3 text-sm">
    <p class="text-xs font-semibold">Qualification(s) this Head of Programme heads</p>
    @if ($note)<p class="text-xs text-[var(--ink-3)]">They wrote: “{{ $note }}”</p>@endif
    @forelse ($quals as $q)
      <label class="flex items-start gap-2"><input type="checkbox" name="programmes[]" value="{{ $q }}" class="mt-0.5 h-4 w-4 rounded accent-[#006699]" @checked(in_array(mb_strtolower($q), array_map('mb_strtolower', $programmes), true))> <span>{{ $q }}</span></label>
    @empty
      <p class="text-xs text-[var(--ink-3)]">No qualifications yet — import the subject list first.</p>
    @endforelse
  </div>
</div>
