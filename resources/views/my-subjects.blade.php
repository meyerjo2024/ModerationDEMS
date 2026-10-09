<x-app-layout title="My subjects">
<div class="mx-auto max-w-4xl space-y-6" x-data="{ q: '' }">
  <div><p class="kicker">Responsibilities</p><h1 class="mt-1 text-3xl font-semibold tracking-tight">My subjects</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Tick the subjects you are responsible for. Examiners can only start assessments for their own subjects, and internal moderators are offered for the subjects they have chosen.</p></div>
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
                  <label class="flex cursor-pointer items-start gap-2.5 rounded-xl px-3 py-2 text-sm hover:bg-slate-900/5 dark:hover:bg-white/10"
                    x-show="!q || {{ \Illuminate\Support\Js::from(mb_strtolower($s->code.' '.$s->name.' '.$qual)) }}.includes(q.toLowerCase())">
                    <input type="checkbox" name="subjects[{{ $role->value }}][]" value="{{ $s->id }}" class="mt-0.5 h-4 w-4 rounded accent-[#0b4ea2]" @checked(in_array($s->id, $chosen[$role->value] ?? [], true))>
                    <span><span class="font-semibold">{{ $s->code }}</span> <span class="text-slate-600 dark:text-zinc-300">{{ $s->name }}</span></span>
                  </label>
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
