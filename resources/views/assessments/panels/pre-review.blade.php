@php
$AK = \App\Enums\AttachmentKind::class;
$f = config('moderation_form');
$cfg = [
  'id' => $a->id,
  'ratings' => collect($f['ratings'])->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values(),
  'questions' => collect($f['s1_questions'])->map(fn ($t, $k) => ['key' => $k, 'n' => substr($k, 1), 'text' => $t])->values(),
];
@endphp
<div x-data="preReview(@js($cfg))" @signed="sig = $event.detail" class="space-y-6">
  <section class="card p-6 sm:p-8">
    <p class="kicker">Gate 1 · Review</p><h2 class="mt-1 text-2xl font-semibold">Assessment paper & memorandum</h2>
    <p class="mb-5 mt-1 text-sm text-slate-500 dark:text-zinc-400">Read the documents and the examiner’s Section 1, complete your ratings and answers below, then approve or send it back with feedback.</p>
    <x-doc-viewer :docs="[['Assessment paper', $a->latestAttachment($AK::Paper)], ['Memorandum', $a->latestAttachment($AK::Memo)]]" />
  </section>

  @include('assessments.partials.form-readonly', ['a' => $a, 'sections' => ['s1']])

  <section class="card p-6 sm:p-8">
    <p class="kicker">Section 1 · Internal moderator</p><h2 class="mt-1 text-xl font-semibold">Ratings and questions</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Rate each criterion: <b>0 Poor · 1 Adequate · 2 Good</b>.</p>
    <ul class="mt-4 divide-y divide-slate-200/60 dark:divide-white/10">
      <template x-for="r in ratings" :key="r.key"><li class="flex flex-wrap items-center justify-between gap-3 py-3">
        <p class="max-w-md text-sm font-medium" x-text="r.label"></p>
        <div role="radiogroup" :aria-label="r.label" class="inline-flex rounded-full bg-slate-900/5 p-1 dark:bg-white/10">
          <template x-for="o in [{v:0,l:'0 Poor'},{v:1,l:'1 Adequate'},{v:2,l:'2 Good'}]" :key="o.v">
            <button type="button" role="radio" :aria-checked="form.ratings[r.key] === o.v" @click="form.ratings[r.key] = o.v"
              :class="form.ratings[r.key] === o.v ? 'bg-accent text-white dark:bg-accent-dark dark:text-black' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400'" class="rounded-full px-3.5 py-1 text-[13px] font-semibold transition" x-text="o.l"></button>
          </template>
        </div>
      </li></template>
    </ul>
    <ul class="mt-5 space-y-4">
      <template x-for="q in questions" :key="q.key"><li class="card-soft space-y-2.5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <p class="max-w-lg text-sm font-medium"><span x-text="q.n + '. '"></span><span x-text="q.text"></span></p>
          <div role="radiogroup" :aria-label="q.text" class="inline-flex rounded-full bg-slate-900/5 p-1 dark:bg-white/10">
            <template x-for="o in [{v:'YES',l:'Yes',on:'bg-emerald-500 text-white'},{v:'NO',l:'No',on:'bg-rose-500 text-white'}]" :key="o.v">
              <button type="button" role="radio" :aria-checked="form.questions[q.key].answer === o.v" @click="form.questions[q.key].answer = o.v"
                :class="form.questions[q.key].answer === o.v ? o.on : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400'" class="rounded-full px-4 py-1 text-[13px] font-semibold transition" x-text="o.l"></button>
            </template>
          </div>
        </div>
        <textarea rows="2" class="field text-sm" maxlength="2000" :placeholder="form.questions[q.key].answer === 'NO' ? 'Comments (required when the answer is No)' : 'Comments (optional)'" x-model="form.questions[q.key].comment"></textarea>
      </li></template>
    </ul>
  </section>

  <section class="card p-6 sm:p-8">
    <p class="kicker">Decision</p>
    <div class="mt-3 flex flex-wrap gap-3">
      <button type="button" class="btn-secondary" @click="sheet = true"><x-icon name="reply" /> Request revision</button>
      <button type="button" class="btn-primary" @click="approving = !approving"><x-icon name="check-circle" /> Approve…</button>
    </div>
    <div x-show="approving" x-cloak x-transition class="mt-6 space-y-5 border-t border-slate-200/60 pt-6 dark:border-white/10">
      <div><label class="label" for="pre-comments">General comments <span class="normal-case tracking-normal text-slate-400">(optional)</span></label><textarea id="pre-comments" rows="3" class="field" x-model="comments"></textarea></div>
      <label class="flex cursor-pointer items-center gap-3 text-sm font-medium"><input type="checkbox" class="h-5 w-5 rounded-md accent-[#006699]" x-model="consensus"> Consensus reached with the examiner</label>
      <x-signoff statement="By signing, I declare that consensus has been reached with the examiner and I approve the assessment." />
      <template x-if="error"><x-notice tone="error"><span x-text="error"></span></x-notice></template>
      <div class="flex justify-end"><button type="button" class="btn-primary" :disabled="!formComplete || !consensus || !sig || busy" @click="approve()"><span x-text="busy === 'approve' ? 'Approving…' : 'Sign & approve'"></span></button></div>
      <p x-show="!formComplete" class="text-right text-xs text-slate-400">Complete every rating and answer every question (explain any “No”).</p>
    </div>
  </section>
  <x-slide-over open="sheet" title="Request revision" subtitle="Your feedback goes straight to the examiner, who can resubmit after changes.">
    <div class="space-y-4">
      <div><label class="label" for="rev-comments">Feedback</label><textarea id="rev-comments" rows="9" class="field" placeholder="Be specific — e.g. “Question 3 sits above level 7 …”" x-model="comments"></textarea></div>
      <template x-if="error"><x-notice tone="error"><span x-text="error"></span></x-notice></template>
      <button type="button" class="btn-primary w-full" :disabled="comments.trim().length < 5 || busy" @click="revise()">Send back to examiner</button>
    </div>
  </x-slide-over>
</div>
