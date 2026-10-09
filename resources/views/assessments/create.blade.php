<x-app-layout title="New assessment">
<form method="POST" action="{{ route('assessments.store') }}" class="card mx-auto max-w-xl space-y-6 p-8">
  @csrf
  <div><p class="kicker">Phase 1 · Pre-assessment</p><h1 class="mt-1 text-3xl font-semibold">New assessment</h1><p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Choose the subject and assessment, then assign who will moderate it.</p></div>
  @if ($subjects->isEmpty())<x-notice tone="warn" title="Choose your subjects first">You can only start assessments for subjects you are responsible for. <a href="{{ route('my-subjects') }}" class="font-semibold underline">Pick them in My subjects</a>.</x-notice>@endif
  @if ($errors->any())<x-notice tone="error">{{ $errors->first() }}</x-notice>@endif
  @php $mods = $internals->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'subjects' => $u->subjects->where('pivot.role', 'INTERNAL_MODERATOR')->pluck('id')->values()])->values(); @endphp
  <div x-data="{ subject: @js((string) old('subject_id')), mods: @js($mods), date: @js(old('assessment_date', '')), internal: @js((string) old('internal_moderator_id')),
    get options() { const m = this.mods.filter(u => u.subjects.includes(Number(this.subject))); return m.length ? m : this.mods; },
    get registered() { return this.mods.some(u => u.subjects.includes(Number(this.subject))); },
    fmt(d) { return d.toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' }); },
    shift(n) { const d = new Date(this.date + 'T00:00:00'); d.setDate(d.getDate() + n); return this.fmt(d); } }" class="space-y-6">
  <div class="grid gap-5 sm:grid-cols-2">
    <div><label class="label" for="subject">Subject</label>
      <select id="subject" name="subject_id" required class="field" x-model="subject" @change="internal = ''"><option value="">Select…</option>@foreach ($subjects as $s)<option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>@endforeach</select></div>
    <div><label class="label" for="number">Assessment number</label><input id="number" name="number" required maxlength="40" class="field" placeholder="e.g. Test 1, Exam" value="{{ old('number') }}"></div>
  </div>
  <div><label class="label" for="adate">Assessment date <span class="normal-case tracking-normal text-slate-400">(optional, sets the deadlines)</span></label>
    <input id="adate" name="assessment_date" type="date" class="field max-w-[220px]" x-model="date">
    <p class="mt-1.5 text-xs text-slate-500 dark:text-zinc-400" x-show="date" x-cloak>Pre-moderation must be finished by <b x-text="shift(-{{ (int) config('dems.pre_moderation_days') }})"></b>; post-moderation by <b x-text="shift({{ (int) config('dems.post_moderation_days') }})"></b>.</p></div>
  <div><label class="label" for="internal">Internal moderator</label>
    <select id="internal" name="internal_moderator_id" required class="field" x-model="internal"><option value="">Select…</option><template x-for="u in options" :key="u.id"><option :value="u.id" x-text="u.name"></option></template></select>
    <p class="mt-1.5 text-xs text-slate-400" x-show="subject && !registered" x-cloak>No moderator has registered for this subject yet, so everyone is listed.</p></div>
  </div>
  <div><label class="label" for="external">External moderator <span class="normal-case tracking-normal text-slate-400">(optional)</span></label>
    <select id="external" name="external_moderator_id" class="field"><option value="">Not required</option>@foreach ($externals as $u)<option value="{{ $u->id }}" @selected(old('external_moderator_id') == $u->id)>{{ $u->name }}</option>@endforeach</select>
    <p class="mt-1.5 text-xs text-slate-400">An external moderator only sees the record after the internal moderator’s final sign-off.</p></div>
  <button class="btn-primary w-full">Continue <x-icon name="arrow-right" /></button>
</form>
</x-app-layout>
