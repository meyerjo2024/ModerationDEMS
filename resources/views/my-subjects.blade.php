<x-app-layout title="My subjects">
<div class="mx-auto max-w-4xl space-y-6" x-data="{ q: '' }">
  <div><p class="kicker">Responsibilities</p><h1 class="mt-1 text-3xl font-semibold tracking-tight">My subjects</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Tick the subjects you are responsible for. As an examiner you can also enter the date of each test (T1, T2 …) so the system reminds you before it is written. Examiners can only start assessments for their own subjects, and internal moderators are offered for the subjects they have chosen.</p></div>
  @if (session('status'))<x-notice tone="success">{{ session('status') }}</x-notice>@endif
  @if (! $roles)<x-notice tone="warn">Only examiners and internal moderators choose subjects.</x-notice>@else
  <form method="POST" action="{{ route('my-subjects.update') }}" class="space-y-6">@csrf @method('PUT')
    <input type="search" x-model="q" placeholder="Search by code, name or qualification…" class="field" aria-label="Search subjects">
    @foreach ($roles as $role)
      <section class="card p-6" x-data="{ n: {{ count($chosen[$role->value] ?? []) }} }">
        <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">As {{ strtolower($role->label()) }}</h2><span class="text-xs text-slate-500" x-text="n + ' selected'"></span></div>
        <div class="mt-4 space-y-5" @change="n = $el.querySelectorAll('input[type=checkbox]:checked').length">
          @foreach ($subjects as $qual => $list)
            <div x-show="!q || {{ \Illuminate\Support\Js::from(mb_strtolower($qual.' '.$list->map(fn ($s) => $s->code.' '.$s->name)->implode(' '))) }}.includes(q.toLowerCase())">
              <p class="kicker mb-2">{{ $qual }}</p>
              <div class="grid gap-1.5 sm:grid-cols-2">
                @foreach ($list as $s)
                  @php $isEx = $role === \App\Enums\Role::Examiner; $on = in_array($s->id, $chosen[$role->value] ?? [], true); @endphp
                  <div x-data="{ on: {{ $on ? 'true' : 'false' }} }" :class="on && {{ $isEx ? 'true' : 'false' }} ? 'sm:col-span-2' : ''"
                    x-show="!q || {{ \Illuminate\Support\Js::from(mb_strtolower($s->code.' '.$s->name.' '.$qual)) }}.includes(q.toLowerCase())">
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-xl px-3 py-2 text-sm hover:bg-[var(--fog)]">
                      <input type="checkbox" name="subjects[{{ $role->value }}][]" value="{{ $s->id }}" class="mt-0.5 h-4 w-4 rounded accent-[#006699]" @checked($on) @change="on = $event.target.checked">
                      <span><span class="font-semibold">{{ $s->code }}</span> <span class="text-slate-600 dark:text-zinc-300">{{ $s->name }}</span></span>
                    </label>
                    @if ($isEx)
                      <div x-show="on" x-cloak class="ml-9 mt-1 rounded-lg bg-[var(--canvas-soft)] p-3">
                        <p class="mb-2 text-xs text-[var(--ink-2)]">Date each test is written. You are reminded {{ (int) config('dems.assessment_reminder_days') }} days before.</p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                          @foreach (config('dems.assessment_numbers') as $n)
                            <label class="text-xs font-semibold text-[var(--ink-2)]">{{ $n }}<input type="date" name="plans[{{ $s->id }}][{{ $n }}]" value="{{ $plans[$s->id][$n] ?? '' }}" class="field mt-0.5 !px-2 !py-1.5 text-xs"></label>
                          @endforeach
                        </div>
                      </div>
                    @endif
                  </div>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>
      </section>
    @endforeach
    <div class="flex justify-end"><button class="btn-primary">Save my subjects</button></div>
  </form>
  @endif
</div>
</x-app-layout>
