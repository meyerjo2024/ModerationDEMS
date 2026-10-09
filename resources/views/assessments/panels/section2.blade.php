@php $AK = \App\Enums\AttachmentKind::class; $MS = \App\Enums\ModerationStage::class;


$marks = $a->latestAttachment($AK::Marks);
$samples = $a->attachmentsOf($AK::SampleScript)->map(fn ($s) => ['id' => $s->id, 'filename' => $s->filename])->all();
$feedback = $a->records->where('decision', 'REVISION_REQUESTED')->where('stage', '!=', $MS::PreModeration)->last();
@endphp
<div x-data="section2(@js(['id' => $a->id, 'marksId' => $marks?->id, 'marksName' => $marks?->filename, 'samples' => $samples]))" @signed="sig = $event.detail" class="space-y-6">
  @if ($feedback)
    <x-notice tone="warn" title="Returned by {{ $feedback->reviewer->name }}"><p class="whitespace-pre-wrap">{{ $feedback->comments }}</p><p class="mt-1 text-xs opacity-70">{{ $feedback->created_at->utc()->format('j M Y, H:i') }} UTC</p></x-notice>
  @endif

  <section class="card p-6 sm:p-8">
    <p class="kicker">Section 2 · Step 1</p><h2 class="mt-1 text-2xl font-semibold">Student marks</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Upload the marks workbook and pick the assessment column (e.g. T1), or enter marks by hand. Statistics are calculated automatically.</p>
    <div class="mt-5 inline-flex gap-1 rounded-full bg-slate-900/5 p-1 dark:bg-white/10" role="tablist">
      @foreach (['excel' => 'Excel / CSV', 'manual' => 'Enter manually'] as $k => $l)
        <button type="button" role="tab" @click="mode = '{{ $k }}'" :class="mode === '{{ $k }}' ? 'bg-white text-slate-900 shadow-sm dark:bg-white/15 dark:text-white' : 'text-slate-500 dark:text-zinc-400'" class="rounded-full px-3.5 py-1.5 text-[13px] font-medium transition">{{ $l }}</button>
      @endforeach
    </div>
    <div x-show="mode === 'excel'" class="mt-5 space-y-4">
      <template x-if="marksName"><div class="flex items-center gap-2 rounded-xl bg-slate-900/[.04] px-3 py-2 text-sm dark:bg-white/5"><x-icon name="file" class="h-4 w-4 text-slate-400" /><span class="truncate font-medium" x-text="marksName"></span></div></template>
      <x-file-drop label="Upload / replace marks workbook" hint=".xlsx or .csv · header in row 1 · one column per assessment" accept=".xlsx,.csv" on-file="upload('MARKS', file)" busy="busy === 'MARKS'" />
      <div x-show="sheets.length" class="grid gap-4 sm:grid-cols-2">
        <div x-show="sheets.length > 1"><label class="label" for="sheet">Sheet</label><select id="sheet" class="field" x-model="sheet" @change="column = 0"><template x-for="s in sheets" :key="s.name"><option :value="s.name" x-text="s.name"></option></template></select></div>
        <div><label class="label" for="column"><x-icon name="table" class="mr-1 inline h-3.5 w-3.5" />Assessment column</label>
          <select id="column" class="field" x-model.number="column"><option value="0">Select a column…</option><template x-for="c in cols" :key="c.index"><option :value="c.index" x-text="`${c.header} · ${c.nonBlank} entries`"></option></template></select></div>
      </div>
    </div>
    <div x-show="mode === 'manual'" x-cloak class="mt-5"><label class="label" for="manual">Marks (one per line)</label><textarea id="manual" rows="6" class="field font-mono text-sm" placeholder="72&#10;48.5&#10;55" x-model="manual"></textarea><p class="mt-1.5 text-xs text-slate-400">Anonymous marks only — no names or student numbers.</p></div>
    <div class="mt-5 max-w-[220px]"><label class="label" for="total">Total marks available</label><input id="total" type="number" min="1" inputmode="decimal" class="field tabular-nums" x-model="total"></div>
  </section>

  <section class="card p-6 sm:p-8">
    <div class="flex items-center justify-between"><div><p class="kicker">Section 2 · Step 2</p><h2 class="mt-1 text-2xl font-semibold">Calculated results</h2></div><x-icon name="spinner" x-show="calculating" class="h-5 w-5 animate-spin text-slate-400" /></div>
    <div class="mt-5">
      <template x-if="preview">
        <div class="space-y-3">
          <div class="space-y-4" x-data="{ pct: (n) => n === null ? '—' : Number(n).toFixed(1) + '%' }">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
              <div class="card-soft p-4"><p class="kicker">Candidates</p><p class="mt-1.5 text-2xl font-semibold tabular-nums" x-text="preview.stats.candidate_count"></p></div>
              <div class="card-soft p-4"><p class="kicker">Pass rate</p><p class="mt-1.5 text-2xl font-semibold tabular-nums" x-text="pct(preview.stats.pass_rate)"></p><p class="mt-0.5 text-xs text-slate-500" x-text="`${preview.stats.pass_count} passed (≥ 50%)`"></p></div>
              <div class="card-soft p-4"><p class="kicker">Class average</p><p class="mt-1.5 text-2xl font-semibold tabular-nums" x-text="pct(preview.stats.class_average)"></p></div>
              <div class="card-soft p-4"><p class="kicker">Highest</p><p class="mt-1.5 text-2xl font-semibold tabular-nums" x-text="pct(preview.stats.highest_mark)"></p></div>
              <div class="card-soft p-4"><p class="kicker">Lowest</p><p class="mt-1.5 text-2xl font-semibold tabular-nums" x-text="pct(preview.stats.lowest_mark)"></p></div>
            </div>
            <div class="card-soft p-4"><p class="kicker mb-3">Mark distribution</p>
              <div class="flex h-28 items-end gap-1.5"><template x-for="(n, i) in preview.stats.distribution" :key="i"><div class="flex h-full flex-1 flex-col items-center justify-end gap-1"><span class="text-[11px] tabular-nums text-slate-500" x-text="n || ''"></span><div class="w-full rounded-t-md" :class="i >= 5 ? 'bg-accent/80 dark:bg-accent-dark/80' : 'bg-slate-300 dark:bg-zinc-600'" :style="`height:${Math.max(n / Math.max(1, ...preview.stats.distribution) * 100, n ? 6 : 1.5)}%`"></div></div></template></div>
              <div class="mt-1.5 flex gap-1.5 text-[10px] text-slate-400"><template x-for="(n, i) in preview.stats.distribution" :key="i"><span class="flex-1 text-center" x-text="i * 10"></span></template></div></div>
            <p x-show="preview.stats.invalid_entries" class="text-xs text-amber-700 dark:text-amber-300" x-text="`${preview.stats.invalid_entries} non-numeric or out-of-range entries were excluded (e.g. “ABS”).`"></p>
          </div>
          <p class="flex items-center gap-1.5 text-xs text-slate-400"><x-icon name="file" class="h-3.5 w-3.5" /> Source: <span x-text="preview.source"></span></p>
        </div>
      </template>
      <template x-if="!preview && calcError"><x-notice tone="error"><span x-text="calcError"></span></x-notice></template>
      <p x-show="!preview && !calcError" class="rounded-2xl border border-dashed border-slate-300/80 px-4 py-10 text-center text-sm text-slate-400 dark:border-white/15">Choose a marks column to see the statistics.</p>
    </div>
  </section>

  <section class="card p-6 sm:p-8">
    <p class="kicker">Section 2 · Step 3</p><h2 class="mt-1 text-2xl font-semibold">Sample scripts <span class="text-base font-normal text-slate-400">(optional)</span></h2>
    <p class="mb-4 mt-1 text-sm text-slate-500 dark:text-zinc-400">Attach marked scripts (high, average and low) for the moderator to review.</p>
    <div class="mb-3 grid gap-2 sm:grid-cols-2"><template x-for="s in samples" :key="s.id"><a :href="'/files/' + s.id" class="flex items-center gap-2 rounded-xl bg-slate-900/[.04] px-3 py-2 text-sm dark:bg-white/5"><x-icon name="file" class="h-4 w-4 text-slate-400" /><span class="truncate font-medium" x-text="s.filename"></span></a></template></div>
    <x-file-drop label="Add a sample script" hint="PDF, PNG or JPG · up to {{ config('dems.max_upload_mb') }} MB each" accept=".pdf,.png,.jpg,.jpeg" on-file="upload('SAMPLE_SCRIPT', file)" busy="busy === 'SAMPLE_SCRIPT'" />
  </section>

  <section class="card p-6 sm:p-8">
    <p class="kicker">Section 2 · Step 4</p><h2 class="mt-1 text-2xl font-semibold">Commentary & signature</h2>
    <div class="mt-5"><label class="label" for="commentary">Comments on student performance</label><textarea id="commentary" rows="5" class="field" maxlength="6000" placeholder="e.g. Most candidates struggled with the essay question on …" x-model="commentary"></textarea></div>
    <div class="mt-6"><x-signoff statement="By signing Section 2, I confirm the marks are complete and the statistics above reflect the final marked results." /></div>
    <template x-if="error"><x-notice tone="error" class="mt-5"><span x-text="error"></span></x-notice></template>
    <div class="mt-6 flex justify-end"><button type="button" class="btn-primary" @click="submit()" :disabled="!ready || busy"><x-icon name="send" /> <span x-text="busy === 'submit' ? 'Submitting…' : 'Sign & submit for final moderation'"></span></button></div>
    <p x-show="!ready" class="mt-3 text-right text-xs text-slate-400">Needs calculated results, commentary and your signature.</p>
  </section>
</div>
