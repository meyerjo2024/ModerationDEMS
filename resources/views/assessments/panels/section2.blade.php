@php $AK = \App\Enums\AttachmentKind::class; $MS = \App\Enums\ModerationStage::class;


$marks = $a->latestAttachment($AK::Marks);
$samples = $a->attachmentsOf($AK::SampleScript)->map(fn ($s) => ['id' => $s->id, 'filename' => $s->filename])->all();
$feedback = $a->records->where('decision', 'REVISION_REQUESTED')->where('stage', '!=', $MS::PreModeration)->last();
@endphp
<div x-data="section2(@js(['id' => $a->id, 'number' => $a->number, 'subjectCode' => $a->subject->code, 'form' => $a->s2_examiner ?? ['registered' => null, 'type_of_assessment' => '', 'answers' => collect(config('moderation_form.s2_examiner_questions'))->map(fn () => '')->all()], 'marksId' => $marks?->id, 'marksName' => $marks?->filename, 'samples' => $samples]))" @signed="sig = $event.detail" class="space-y-6">
  @if ($feedback)
    <x-notice tone="warn" title="Returned by {{ $feedback->reviewer->name }}"><p class="whitespace-pre-wrap">{{ $feedback->comments }}</p><p class="mt-1 text-xs opacity-70">{{ $feedback->created_at->utc()->format('j M Y, H:i') }} UTC</p></x-notice>
  @endif

  <section class="card p-6 sm:p-8">
    <p class="kicker">Section 2 · Step 1</p><h2 class="mt-1 text-2xl font-semibold">Student marks</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Upload the class-list marksheet (<span class="font-medium">.xls</span>, .xlsx or .csv) and pick the test (e.g. <span class="font-medium">T1</span>), or enter marks by hand. Statistics are calculated automatically.</p>
    <div class="mt-5 inline-flex gap-1 rounded-full bg-slate-900/5 p-1 dark:bg-white/10" role="tablist">
      @foreach (['excel' => 'Marksheet file', 'manual' => 'Enter manually'] as $k => $l)
        <button type="button" role="tab" @click="mode = '{{ $k }}'" :class="mode === '{{ $k }}' ? 'bg-white text-slate-900 shadow-sm dark:bg-white/15 dark:text-white' : 'text-slate-500 dark:text-zinc-400'" class="rounded-full px-3.5 py-1.5 text-[13px] font-medium transition">{{ $l }}</button>
      @endforeach
    </div>
    <div x-show="mode === 'excel'" class="mt-5 space-y-4">
      <template x-if="marksName"><div class="flex items-center gap-2 rounded-xl bg-slate-900/[.04] px-3 py-2 text-sm dark:bg-white/5"><x-icon name="file" class="h-4 w-4 text-slate-400" /><span class="truncate font-medium" x-text="marksName"></span></div></template>
      <x-file-drop label="Upload / replace marks workbook" hint="Class-list marksheet (.xls) · or .xlsx / .csv with headings in row 1" accept=".xls,.xlsx,.csv" on-file="upload('MARKS', file)" busy="busy === 'MARKS'" />
      <template x-if="current && current.format === 'class-list'">
        <div class="card-soft flex flex-wrap items-center gap-x-5 gap-y-1 px-4 py-3 text-sm">
          <span class="inline-flex items-center gap-1.5 font-semibold text-emerald-600 dark:text-emerald-400"><x-icon name="check-circle" class="h-4 w-4" /> Class list recognised</span>
          <span x-text="[current.meta.code, current.meta.year, current.meta.lecturer].filter(Boolean).join(' · ')"></span>
          <span x-show="current.meta.students" class="text-slate-500 dark:text-zinc-400" x-text="current.meta.students + ' students listed'"></span>
        </div>
      </template>
      <div x-show="sheets.length" class="grid gap-4 sm:grid-cols-2">
        <div x-show="sheets.length > 1"><label class="label" for="sheet">Sheet</label><select id="sheet" class="field" x-model="sheet" @change="autoPick()"><template x-for="s in sheets" :key="s.name"><option :value="s.name" x-text="s.name"></option></template></select></div>
        <div><label class="label" for="column"><x-icon name="table" class="mr-1 inline h-3.5 w-3.5" /><span x-text="current && current.format === 'class-list' ? 'Test (T1, T2 …)' : 'Assessment column'"></span></label>
          <select id="column" class="field" x-model.number="column"><option value="0">Select a column…</option><template x-for="c in cols" :key="c.index"><option :value="c.index" :disabled="c.nonBlank === 0" x-text="`${c.header}${c.weight ? ' · ' + c.weight + '% of year mark' : ''} · ${c.nonBlank ? c.nonBlank + ' marks' : 'no marks yet'}`"></option></template></select></div>
      </div>
    </div>
    <div x-show="mode === 'manual'" x-cloak class="mt-5"><label class="label" for="manual">Marks (one per line)</label><textarea id="manual" rows="6" class="field font-mono text-sm" placeholder="72&#10;48.5&#10;55" x-model="manual"></textarea><p class="mt-1.5 text-xs text-slate-400">Anonymous marks only — no names or student numbers.</p></div>
    <div class="mt-5 max-w-[220px]"><label class="label" for="total">Total marks available</label><input id="total" type="number" min="1" inputmode="decimal" class="field tabular-nums" x-model="total">
      <p x-show="current && current.format === 'class-list'" x-cloak class="mt-1.5 text-xs text-slate-400">Marks on a class list are already percentages (out of 100).</p></div>
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
          <p x-show="preview.info && preview.info.enrolled" class="text-xs text-slate-500 dark:text-zinc-400" x-text="`${preview.stats.candidate_count} of ${preview.info && preview.info.enrolled} listed students have a mark in this test` + (preview.info && preview.info.weight ? ` · test weight ${preview.info.weight}% of the year mark` : '')"></p>
          <template x-for="w in (preview.warnings || [])" :key="w"><x-notice tone="warn"><span x-text="w"></span></x-notice></template>
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
    <p class="kicker">Section 2 · Step 4</p><h2 class="mt-1 text-2xl font-semibold">Candidates & comments</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">These answers appear in Section 2 of the official moderation report. Pass rate, highest and average mark come from the calculation above.</p>
    <div class="mt-5 grid gap-4 sm:grid-cols-3">
      <div><label class="label" for="registered">Total number of (reg.) candidates</label><input id="registered" type="number" min="0" inputmode="numeric" class="field tabular-nums" x-model.number="form.registered"><p class="mt-1 text-xs text-slate-400" x-show="preview && preview.info && preview.info.enrolled">From the class list.</p></div>
      <div><label class="label">No. of candidates absent</label><div class="field !bg-slate-100/70 tabular-nums dark:!bg-white/5" x-text="absent === null ? '—' : absent"></div></div>
      <div><label class="label" for="atype">Type of assessment</label><input id="atype" list="atypes" class="field" maxlength="80" placeholder="e.g. Written test" x-model="form.type_of_assessment"><datalist id="atypes"><option value="Written test"><option value="Examination"><option value="Practical"><option value="Oral"><option value="Project"><option value="Assignment"></datalist></div>
    </div>
    <div class="mt-5 space-y-4">
      @foreach (config('moderation_form.s2_examiner_questions') as $k => $text)
        <div><label class="label !normal-case !tracking-normal !text-sm !font-medium" for="q-{{ $k }}">{{ substr($k, 1) }}. {{ $text }}</label><textarea id="q-{{ $k }}" rows="3" class="field" maxlength="3000" x-model="form.answers.{{ $k }}"></textarea></div>
      @endforeach
    </div>
    <div class="mt-6"><x-signoff statement="By signing Section 2, I confirm the marks are complete and the statistics and answers above are accurate." /></div>
    <template x-if="error"><x-notice tone="error" class="mt-5"><span x-text="error"></span></x-notice></template>
    <div class="mt-6 flex justify-end"><button type="button" class="btn-primary" @click="submit()" :disabled="!ready || busy"><x-icon name="send" /> <span x-text="busy === 'submit' ? 'Submitting…' : 'Sign & submit for final moderation'"></span></button></div>
    <p x-show="!ready" class="mt-3 text-right text-xs text-slate-400">Needs calculated results, the candidate details, all five answers and your signature.</p>
  </section>
</div>
