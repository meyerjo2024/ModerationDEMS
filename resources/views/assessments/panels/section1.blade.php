@php
use App\Enums\ModerationStage;
$f = config('moderation_form');
$s = $a->s1_examiner ?? [];
$form = [
  'period' => $s['period'] ?? 'first', 'year' => $s['year'] ?? (int) date('Y'), 'heqf_level' => $s['heqf_level'] ?? 6,
  'subject_level' => $s['subject_level'] ?? $a->year_level ?? '', 'qualification' => $s['qualification'] ?? '', 'qualification_code' => $s['qualification_code'] ?? '',
  'assessment_date' => $s['assessment_date'] ?? '',
  'weights' => collect(array_keys($f['question_types']))->mapWithKeys(fn ($k) => [$k => $s['weights'][$k] ?? ''])->all(),
];
$docList = $a->documents()->map(fn ($d) => ['id' => $d->id, 'label' => $d->doc_label, 'filename' => $d->filename])->values()->all();
$feedback = $a->records->where('stage', ModerationStage::PreModeration)->where('decision', 'REVISION_REQUESTED')->last();
@endphp
<div x-data="section1(@js(['id' => $a->id, 'form' => $form, 'docs' => $docList]))" @signed="sig = $event.detail" class="space-y-6">
  @if ($feedback && $a->status === \App\Enums\AssessmentStatus::RevisionRequested)
    <x-notice tone="warn" title="Revision requested by {{ $feedback->reviewer->name }}"><p class="whitespace-pre-wrap">{{ $feedback->comments }}</p><p class="mt-1 text-xs opacity-70">{{ $feedback->created_at->utc()->format('j M Y, H:i') }} UTC</p></x-notice>
    @if (\App\Models\DocumentComment::where('assessment_id', $a->id)->exists())
      <section class="card p-6 sm:p-8">
        <p class="kicker">Reviewer comments</p><h2 class="mt-1 text-xl font-semibold">Comments on your documents</h2>
        <p class="mb-4 mt-1 text-sm text-slate-500 dark:text-zinc-400">The moderator commented on your documents. Reply to a comment, make the changes, upload the revised files below, and mark each comment as addressed.</p>
        @php $AK = \App\Enums\AttachmentKind::class; @endphp
        <x-doc-viewer :docs="$a->documents()->map(fn ($d) => [$d->doc_label, $d])->all()" />
      </section>
    @endif
  @endif

  <section class="card p-6 sm:p-8">
    <p class="kicker">Section 1 · Pre-assessment</p><h2 class="mt-1 text-2xl font-semibold">Assessment details</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">These fields appear on the official moderation report. Subject, assessment number and moderators are taken from the record.</p>
    <dl class="card-soft mt-5 grid gap-x-6 gap-y-2 p-4 text-sm sm:grid-cols-3">
      <div><dt class="kicker">Subject</dt><dd class="font-medium">{{ $a->subject->code }} — {{ $a->subject->name }}</dd></div>
      <div><dt class="kicker">Assessment no.</dt><dd class="font-medium">{{ $a->number_label }}{{ $a->label !== '' ? ' ('.$a->label.')' : '' }}</dd></div>
      <div><dt class="kicker">Examiner</dt><dd class="font-medium">{{ $a->examiner->name }}</dd></div>
      <div><dt class="kicker">Internal moderator</dt><dd class="font-medium">{{ $a->internalModerator->name }}</dd></div>
      <div><dt class="kicker">External moderator</dt><dd class="font-medium">{{ $a->externalModerator?->name ?? 'Not required' }}</dd></div>
    </dl>
    <div class="mt-5 grid gap-4 sm:grid-cols-3">
      <div><label class="label" for="period">Assessment period</label><select id="period" class="field" x-model="form.period">@foreach ($f['periods'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
      <div><label class="label" for="year">Year</label><input id="year" type="number" min="2000" max="2100" class="field tabular-nums" x-model="form.year"></div>
      <div><label class="label" for="adate">Assessment date</label><input id="adate" type="date" class="field" x-model="form.assessment_date"></div>
      <div><label class="label" for="heqf">HEQF level of subject</label><select id="heqf" class="field" x-model="form.heqf_level">@foreach ([5, 6, 7, 8, 9, 10] as $l)<option value="{{ $l }}">Level {{ $l }}</option>@endforeach</select></div>
      <div><label class="label" for="slevel">Year level</label><select id="slevel" class="field" x-model="form.subject_level"><option value="">Select…</option>@foreach (array_unique(array_merge($f['levels'], array_filter([$form['subject_level']]))) as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach</select></div>
      <div></div>
      <div class="sm:col-span-2"><label class="label" for="qual">Qualification</label><input id="qual" class="field" maxlength="160" placeholder="e.g. National Diploma: Emergency Medical Care" x-model="form.qualification"></div>
      <div><label class="label" for="qcode">Qualification code</label><input id="qcode" class="field" maxlength="40" x-model="form.qualification_code"></div>
    </div>
  </section>

  <section class="card p-6 sm:p-8">
    <p class="kicker">Section 1 · Table: levels of complexity of assessments</p><h2 class="mt-1 text-2xl font-semibold">Types of questions</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Enter the <b>% weighting across the task</b> for each type of question (or project assessment criteria). Leave unused types empty. The total must be 100 %.</p>
    <div class="mt-5 space-y-3">
      @foreach ($f['question_types'] as $k => $t)
        <div class="card-soft grid gap-3 p-4 sm:grid-cols-[1fr_130px] sm:items-start">
          <div><p class="text-sm font-semibold">{{ $t['label'] }}</p>
            <details class="mt-1 text-sm text-slate-500 dark:text-zinc-400"><summary class="cursor-pointer select-none text-xs font-medium text-accent dark:text-accent-dark">Show full description</summary><p class="mt-1.5">{{ $t['text'] }}</p></details></div>
          <div><label class="label" for="w-{{ $k }}">% weighting</label><input id="w-{{ $k }}" type="number" min="0" max="100" step="any" inputmode="decimal" class="field tabular-nums" x-model="form.weights.{{ $k }}"></div>
        </div>
      @endforeach
    </div>
    <div class="mt-4 flex items-center justify-end gap-3" aria-live="polite">
      <div class="h-2 w-32 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10"><div class="h-full rounded-full transition-all" :class="total === 100 ? 'bg-emerald-500' : total > 100 ? 'bg-rose-500' : 'bg-accent'" :style="`width:${Math.min(total, 100)}%`"></div></div>
      <span class="text-sm font-semibold tabular-nums" :class="total === 100 ? 'text-emerald-600 dark:text-emerald-400' : total > 100 ? 'text-rose-600' : 'text-slate-600 dark:text-zinc-300'" x-text="`${total}% / 100%`"></span>
    </div>
  </section>

  <section class="card p-6 sm:p-8">
    <p class="kicker">Documents</p><h2 class="mt-1 text-2xl font-semibold">Assessment documents</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Upload the exam paper — it already contains the memorandum, so no separate memo is needed. Add as many other documents as the assessment has (annexures, formula sheets, a second paper…). Reviewers read every one inline. Uploading under a name you already used replaces that document.</p>
    <ul class="mt-5 divide-y divide-[var(--divider)] text-sm">
      <template x-for="d in docs" :key="d.id"><li class="flex items-center justify-between gap-3 py-2.5">
        <a :href="'/files/' + d.id" class="flex min-w-0 items-center gap-2 hover:underline"><x-icon name="file" class="h-4 w-4 shrink-0 text-slate-400" /><span class="font-semibold" x-text="d.label"></span><span class="truncate text-[var(--ink-3)]" x-text="d.filename"></span></a>
        <button type="button" class="text-xs font-semibold text-rose-600" @click="removeDoc(d)" :disabled="busy">Remove</button></li></template>
      <li x-show="!docs.length" class="py-3 text-[var(--ink-3)]">No documents yet.</li>
    </ul>
    <div class="mt-5 grid gap-3 sm:grid-cols-[1fr_1.4fr] sm:items-end">
      <div><label class="label" for="doclabel">Document name</label><input id="doclabel" class="field" x-model="newLabel" list="doclabels" placeholder="e.g. Assessment paper, Annexure A"><datalist id="doclabels"><option value="Assessment paper"><option value="Annexure A"><option value="Formula sheet"><option value="Answer book"></datalist></div>
      <x-file-drop label="Upload document" hint="PDF or Word · up to {{ config('dems.max_upload_mb') }} MB" accept=".pdf,.doc,.docx" on-file="addDoc(file)" busy="busy === 'doc'" />
    </div>
  </section>

  <section class="card p-6 sm:p-8">
    <p class="kicker">Sign-off</p><h2 class="mt-1 text-2xl font-semibold">Sign & submit for pre-moderation</h2>
    <div class="mt-5"><x-signoff statement="By signing, I confirm the details and weightings above are accurate and the attached documents are final for moderation." /></div>
    <template x-if="error"><x-notice tone="error" class="mt-5"><span x-text="error"></span></x-notice></template>
    <template x-if="saved"><x-notice tone="success" class="mt-5"><span x-text="saved"></span></x-notice></template>
    <div class="mt-6 flex flex-wrap justify-end gap-3">
      <button type="button" class="btn-secondary" @click="saveDraft()" :disabled="busy"><x-icon name="save" /> Save draft</button>
      <button type="button" class="btn-primary" @click="submit()" :disabled="!ready || busy"><x-icon name="send" /> <span x-text="busy === 'submit' ? 'Submitting…' : 'Sign & submit'"></span></button>
    </div>
    <p x-show="!ready" class="mt-3 text-right text-xs text-slate-400"><span x-show="missing">Complete all details. </span><span x-show="total !== 100">Weightings must total 100%. </span><span x-show="!docs.length">Upload the assessment paper. </span><span x-show="!sig">Sign and enter your password.</span></p>
  </section>
</div>
