@php $AK = \App\Enums\AttachmentKind::class; $MS = \App\Enums\ModerationStage::class;


$rows = collect($a->question_types ?? [])->map(fn ($r) => ['type' => $r['type'], 'weighting' => $r['weighting'], 'heqf_level' => $r['heqf_level'], 'aligned' => (bool) $r['aligned'], 'comment' => $r['comment'] ?? ''])->all();
$f = fn ($k) => ($att = $a->latestAttachment($k)) ? ['id' => $att->id, 'filename' => $att->filename] : null;
$feedback = $a->records->where('stage', $MS::PreModeration)->where('decision', 'REVISION_REQUESTED')->last();
@endphp
<div x-data="section1(@js(['id' => $a->id, 'rows' => $rows, 'files' => ['PAPER' => $f($AK::Paper), 'MEMO' => $f($AK::Memo)]]))" @signed="sig = $event.detail" class="space-y-6">
  @if ($feedback && $a->status === \App\Enums\AssessmentStatus::RevisionRequested)
    <x-notice tone="warn" title="Revision requested by {{ $feedback->reviewer->name }}"><p class="whitespace-pre-wrap">{{ $feedback->comments }}</p><p class="mt-1 text-xs opacity-70">{{ $feedback->created_at->utc()->format('j M Y, H:i') }} UTC</p></x-notice>
  @endif

  <section class="card p-6 sm:p-8">
    <p class="kicker">Section 1</p><h2 class="mt-1 text-2xl font-semibold">Types of questions</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Assign each question type its weighting and check alignment with the HEQF level descriptors.</p>
    <div class="mt-6 space-y-3">
      <template x-for="(r, i) in rows" :key="i">
        <div class="card-soft grid gap-3 p-4 sm:grid-cols-[1.6fr_.8fr_.8fr_auto] sm:items-end">
          <div><label class="label">Question type</label><input list="qtypes" class="field" placeholder="e.g. Essay" maxlength="80" x-model="r.type"></div>
          <div><label class="label">Weighting %</label><input type="number" min="0" max="100" step="any" inputmode="decimal" class="field tabular-nums" x-model="r.weighting"></div>
          <div><label class="label">HEQF level</label><select class="field" x-model="r.heqf_level">@foreach ([5, 6, 7, 8, 9, 10] as $l)<option value="{{ $l }}">Level {{ $l }}</option>@endforeach</select></div>
          <div class="flex items-center justify-between gap-3 sm:justify-end">
            <button type="button" role="switch" :aria-checked="r.aligned" @click="r.aligned = !r.aligned" class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-zinc-300">
              <span class="relative h-6 w-10 rounded-full transition-colors" :class="r.aligned ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-zinc-600'"><span class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all" :class="r.aligned ? 'left-[18px]' : 'left-0.5'"></span></span>Aligned</button>
            <button type="button" aria-label="Remove row" :disabled="rows.length === 1" @click="remove(i)" class="rounded-full p-2 text-slate-400 hover:bg-rose-500/10 hover:text-rose-500 disabled:opacity-30"><x-icon name="trash" /></button>
          </div>
          <div class="sm:col-span-4"><input class="field !py-2 text-sm" placeholder="Alignment comment (optional)" maxlength="500" x-model="r.comment"></div>
        </div>
      </template>
      <datalist id="qtypes">@foreach (['Multiple choice', 'Short answer', 'Essay', 'Case study', 'Calculation', 'Practical / coding'] as $s)<option value="{{ $s }}">@endforeach</datalist>
    </div>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
      <button type="button" class="btn-secondary" @click="add()" :disabled="rows.length >= 15"><x-icon name="plus" /> Add question type</button>
      <div class="flex items-center gap-3" aria-live="polite">
        <div class="h-2 w-32 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10"><div class="h-full rounded-full transition-all" :class="total === 100 ? 'bg-emerald-500' : total > 100 ? 'bg-rose-500' : 'bg-accent'" :style="`width:${Math.min(total, 100)}%`"></div></div>
        <span class="text-sm font-semibold tabular-nums" :class="total === 100 ? 'text-emerald-600 dark:text-emerald-400' : total > 100 ? 'text-rose-600' : 'text-slate-600 dark:text-zinc-300'" x-text="`${total}% / 100%`"></span>
      </div>
    </div>
  </section>

  <section class="card p-6 sm:p-8">
    <p class="kicker">Documents</p><h2 class="mt-1 text-2xl font-semibold">Assessment paper & memorandum</h2>
    <div class="mt-5 grid gap-5 sm:grid-cols-2">
      @foreach ([['PAPER', 'Draft assessment paper'], ['MEMO', 'Memorandum']] as [$kind, $label])
        <div class="space-y-2"><p class="label !mb-0">{{ $label }}</p>
          <template x-if="files.{{ $kind }}"><a :href="'/files/' + files.{{ $kind }}.id" class="flex items-center gap-2 rounded-xl bg-slate-900/[.04] px-3 py-2 text-sm hover:bg-slate-900/[.07] dark:bg-white/5"><x-icon name="file" class="h-4 w-4 shrink-0 text-slate-400" /><span class="truncate font-medium" x-text="files.{{ $kind }}.filename"></span></a></template>
          <x-file-drop :label="'Upload / replace '.strtolower($label)" hint="PDF or Word · up to {{ config('dems.max_upload_mb') }} MB" accept=".pdf,.doc,.docx" on-file="upload('{{ $kind }}', file)" busy="busy === '{{ $kind }}'" />
        </div>
      @endforeach
    </div>
  </section>

  <section class="card p-6 sm:p-8">
    <p class="kicker">Sign-off</p><h2 class="mt-1 text-2xl font-semibold">Sign & submit for pre-moderation</h2>
    <div class="mt-5"><x-signoff statement="By signing, I confirm the question types, weightings and HEQF alignment above are accurate and the attached paper and memorandum are final for moderation." /></div>
    <template x-if="error"><x-notice tone="error" class="mt-5"><span x-text="error"></span></x-notice></template>
    <template x-if="saved"><x-notice tone="success" class="mt-5"><span x-text="saved"></span></x-notice></template>
    <div class="mt-6 flex flex-wrap justify-end gap-3">
      <button type="button" class="btn-secondary" @click="saveDraft()" :disabled="busy"><x-icon name="save" /> Save draft</button>
      <button type="button" class="btn-primary" @click="submit()" :disabled="!ready || busy"><x-icon name="send" /> <span x-text="busy === 'submit' ? 'Submitting…' : 'Sign & submit'"></span></button>
    </div>
    <p x-show="!ready" class="mt-3 text-right text-xs text-slate-400"><span x-show="total !== 100">Weightings must total 100%. </span><span x-show="!files.PAPER || !files.MEMO">Upload both documents to continue. </span><span x-show="!sig">Sign and enter your password.</span></p>
  </section>
</div>
