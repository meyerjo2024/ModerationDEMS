@php $AK = \App\Enums\AttachmentKind::class; $MS = \App\Enums\ModerationStage::class;


$external = $stage === 'external';
$willComplete = $external || ! $a->external_moderator_id;
$scripts = $a->attachmentsOf($AK::SampleScript)->values()->map(fn ($s, $i) => ['Script '.($i + 1), $s])->all();
$prior = $a->records->where('stage', $MS::FinalInternal)->where('decision', 'APPROVED')->last();
$checks = collect(config('dems.quality_checks'))->map(fn ($q, $id) => ['id' => $id, 'question' => $q])->values();
@endphp
<div x-data="finalReview(@js(['id' => $a->id, 'checks' => $checks]))" @signed="sig = $event.detail" class="space-y-6">
  <section class="card p-6 sm:p-8">
    <p class="kicker">{{ $external ? 'Gate 3 · External moderation' : 'Gate 2 · Final review' }}</p><h2 class="mt-1 text-2xl font-semibold">Results & examiner commentary</h2>
    <div class="mt-5">@include('assessments.partials.stats', ['a' => $a])</div>
    @if ($a->marks_source)<p class="mt-3 text-xs text-slate-400">Source: {{ collect(explode(' › ', $a->marks_source))->slice(1)->implode(' › ') ?: 'Entered manually' }}</p>@endif
    <div class="card-soft mt-5 p-4"><p class="kicker">Examiner’s commentary</p><p class="mt-1.5 whitespace-pre-wrap text-sm">{{ $a->examiner_commentary }}</p></div>
    @if ($external && $prior)<div class="card-soft mt-4 p-4"><p class="kicker">Internal moderator’s sign-off · {{ $prior->reviewer->name }}</p><p class="mt-1.5 text-sm">{{ $prior->comments ?: 'Approved — no additional comments.' }} <span class="text-slate-400">({{ $prior->scripts_sampled }} scripts sampled)</span></p></div>@endif
  </section>

  <section class="card p-6 sm:p-8"><p class="kicker">Evidence</p><h2 class="mb-4 mt-1 text-xl font-semibold">Sample scripts & assessment documents</h2>
    <x-doc-viewer :docs="array_merge($scripts, [['Assessment paper', $a->latestAttachment($AK::Paper)], ['Memorandum', $a->latestAttachment($AK::Memo)]])" /></section>

  <section class="card p-6 sm:p-8">
    <p class="kicker">Quality check</p><h2 class="mt-1 text-xl font-semibold">Marking accuracy, fairness & consistency</h2>
    <ul class="mt-5 divide-y divide-slate-200/60 dark:divide-white/10">
      <template x-for="c in checks" :key="c.id"><li class="space-y-2.5 py-4 first:pt-0">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <p class="max-w-lg text-sm font-medium" x-text="c.question"></p>
          <div role="radiogroup" :aria-label="c.question" class="inline-flex rounded-full bg-slate-900/5 p-1 dark:bg-white/10">
            <template x-for="o in [{v:'YES',l:'Yes',on:'bg-emerald-500 text-white'},{v:'NO',l:'No',on:'bg-rose-500 text-white'},{v:'NA',l:'N/A',on:'bg-slate-500 text-white'}]" :key="o.v">
              <button type="button" role="radio" :aria-checked="answers[c.id].answer === o.v" @click="answers[c.id].answer = o.v" :class="answers[c.id].answer === o.v ? o.on : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400'" class="rounded-full px-3.5 py-1 text-[13px] font-semibold transition" x-text="o.l"></button>
            </template>
          </div>
        </div>
        <input x-show="answers[c.id].answer === 'NO'" x-cloak class="field !py-2 text-sm" placeholder="Explain your concern (required)" maxlength="500" x-model="answers[c.id].comment">
      </li></template>
    </ul>
    <div class="mt-4 grid gap-5 sm:grid-cols-2"><div><label class="label" for="scripts">Scripts sampled</label><input id="scripts" type="number" min="0" class="field tabular-nums" x-model="scripts"></div></div>
    <div class="mt-5"><label class="label" for="final-comments">Comments <span class="normal-case tracking-normal text-slate-400">(optional)</span></label><textarea id="final-comments" rows="3" class="field" x-model="comments"></textarea></div>
  </section>

  <section class="card p-6 sm:p-8">
    <p class="kicker">Sign-off</p><h2 class="mt-1 text-xl font-semibold">{{ $willComplete ? 'Final signature & completion' : 'Final signature' }}</h2>
    <label class="mt-5 flex cursor-pointer items-center gap-3 text-sm font-medium"><input type="checkbox" class="h-5 w-5 rounded-md accent-[#0b4ea2]" x-model="consensus"> Consensus reached</label>
    <div class="mt-5"><x-signoff statement="By signing, I confirm I have reviewed the statistics and sample scripts and that the quality checks above are my honest assessment." /></div>
    <x-notice tone="info" class="mt-5">{{ $willComplete ? 'Approving completes moderation: the PDF report is generated, archived and e-mailed to the Head of Department.' : 'After your approval the record is forwarded to the external moderator.' }}</x-notice>
    <template x-if="error"><x-notice tone="error" class="mt-4"><span x-text="error"></span></x-notice></template>
    <div class="mt-6 flex flex-wrap justify-end gap-3">
      <button type="button" class="btn-secondary" @click="sheet = true"><x-icon name="reply" /> Return to examiner</button>
      <button type="button" class="btn-primary" @click="approve()" :disabled="!complete || !consensus || !sig || scripts === '' || busy"><x-icon name="check-circle" /> <span x-text="busy === 'approve' ? 'Approving…' : 'Sign & approve'"></span></button>
    </div>
  </section>

  <x-slide-over open="sheet" title="Return to examiner" subtitle="The examiner will correct Section 2 and resubmit; sign-offs restart from Gate 2.">
    <div class="space-y-4">
      <div><label class="label" for="ret">Feedback</label><textarea id="ret" rows="9" class="field" x-model="comments"></textarea></div>
      <template x-if="error"><x-notice tone="error"><span x-text="error"></span></x-notice></template>
      <button type="button" class="btn-primary w-full" :disabled="comments.trim().length < 5 || busy" @click="revise()">Send back</button>
    </div>
  </x-slide-over>
</div>
