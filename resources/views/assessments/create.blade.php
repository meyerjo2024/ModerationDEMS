<x-app-layout title="New assessment">
<form method="POST" action="{{ route('assessments.store') }}" class="card mx-auto max-w-xl space-y-6 p-8">
  @csrf
  <div><p class="kicker">Phase 1 · Pre-assessment</p><h1 class="mt-1 text-3xl font-semibold">New assessment</h1><p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Choose the subject and assessment, then assign who will moderate it.</p></div>
  @if ($subjects->isEmpty())<x-notice tone="warn" title="No subjects yet">Ask your Head of Department to add subjects in Admin.</x-notice>@endif
  @if ($errors->any())<x-notice tone="error">{{ $errors->first() }}</x-notice>@endif
  <div class="grid gap-5 sm:grid-cols-2">
    <div><label class="label" for="subject">Subject code</label>
      <select id="subject" name="subject_id" required class="field"><option value="">Select…</option>@foreach ($subjects as $s)<option value="{{ $s->id }}" @selected(old('subject_id') == $s->id)>{{ $s->code }} — {{ $s->name }}</option>@endforeach</select></div>
    <div><label class="label" for="number">Assessment number</label><input id="number" name="number" required maxlength="40" class="field" placeholder="e.g. Test 1, Exam" value="{{ old('number') }}"></div>
  </div>
  <div><label class="label" for="internal">Internal moderator</label>
    <select id="internal" name="internal_moderator_id" required class="field"><option value="">Select…</option>@foreach ($internals as $u)<option value="{{ $u->id }}" @selected(old('internal_moderator_id') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
  <div><label class="label" for="external">External moderator <span class="normal-case tracking-normal text-slate-400">(optional)</span></label>
    <select id="external" name="external_moderator_id" class="field"><option value="">Not required</option>@foreach ($externals as $u)<option value="{{ $u->id }}" @selected(old('external_moderator_id') == $u->id)>{{ $u->name }}</option>@endforeach</select>
    <p class="mt-1.5 text-xs text-slate-400">An external moderator only sees the record after the internal moderator’s final sign-off.</p></div>
  <button class="btn-primary w-full">Continue <x-icon name="arrow-right" /></button>
</form>
</x-app-layout>
