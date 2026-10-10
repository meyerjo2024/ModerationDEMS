@php
$AK = \App\Enums\AttachmentKind::class;
$f = config('moderation_form');
$external = $stage === 'external';
$scripts = $a->attachmentsOf($AK::SampleScript)->values()->map(fn ($s, $i) => ['Script '.($i + 1), $s])->all();
$cfg = [
  'id' => $a->id, 'mode' => $external ? 'external' : 'internal',
  'answers' => $external ? [] : collect($f['s2_moderator_questions'])->map(fn ($t, $k) => ['key' => $k, 'n' => substr($k, 1), 'text' => $t])->values(),
  'items' => collect($external ? $f['comment_items'] + $f['s3_extra_items'] : $f['comment_items'])->map(fn ($t, $k) => ['key' => $k, 'text' => $t])->values(),
  'adjustments' => $f['adjustments'],
];
$willComplete = ! $external && ! $a->external_moderator_id;
@endphp
<div x-data="finalReview(@js($cfg))" @signed="sig = $event.detail" class="space-y-6">
  <section class="card p-6 sm:p-8">
    <p class="kicker">{{ $external ? 'Gate 3 · External moderation' : 'Gate 2 · Final review' }}</p>
    <h2 class="mt-1 text-2xl font-semibold">Evidence</h2>
    <p class="mb-4 mt-1 text-sm text-slate-500 dark:text-zinc-400">Sample scripts, the assessment paper and the memorandum. The examiner’s results and answers follow below.</p>
    <x-doc-viewer :docs="array_merge($scripts, [['Assessment paper', $a->latestAttachment($AK::Paper)], ['Memorandum', $a->latestAttachment($AK::Memo)]])" />
  </section>

  @include('assessments.partials.form-readonly', ['a' => $a, 'sections' => $external ? ['s1', 's2'] : ['s1', 's2']])

  <section class="card p-6 sm:p-8">
    <p class="kicker">{{ $external ? 'Section 3 · External moderator' : 'Section 2 · Internal moderator' }}</p>
    <h2 class="mt-1 text-xl font-semibold">{{ $external ? 'Your comments' : 'Questions 6 to 8' }}</h2>

    <template x-if="mode === 'internal'"><div class="mt-5 space-y-4">
      <template x-for="q in answers" :key="q.key"><div>
        <label class="label !normal-case !tracking-normal !text-sm !font-medium" :for="'a-' + q.key" x-text="q.n + '. ' + q.text"></label>
        <textarea :id="'a-' + q.key" rows="3" class="field" maxlength="3000" x-model="form.answers[q.key]"></textarea>
      </div></template>
      <p class="pt-2 text-sm font-medium">8. Please comment on the following:</p>
    </div></template>

    <div class="mt-4 space-y-4">
      <template x-for="it in items" :key="it.key"><div>
        <label class="label !normal-case !tracking-normal !text-sm !font-medium !italic" :for="'i-' + it.key" x-text="it.text"></label>
        <textarea :id="'i-' + it.key" rows="3" class="field" maxlength="3000" x-model="form.items[it.key]"></textarea>
      </div></template>
    </div>

    <div class="card-soft mt-5 space-y-3 p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm font-medium" x-text="adjustments"></p>
        <div role="radiogroup" aria-label="General adjustments recommended" class="inline-flex rounded-full bg-slate-900/5 p-1 dark:bg-white/10">
          <template x-for="o in [{v:'YES',l:'Yes',on:'bg-amber-500 text-white'},{v:'NO',l:'No',on:'bg-emerald-500 text-white'}]" :key="o.v">
            <button type="button" role="radio" :aria-checked="form.adjustments.recommended === o.v" @click="form.adjustments.recommended = o.v"
              :class="form.adjustments.recommended === o.v ? o.on : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400'" class="rounded-full px-4 py-1 text-[13px] font-semibold transition" x-text="o.l"></button>
          </template>
        </div>
      </div>
      <div x-show="form.adjustments.recommended === 'YES'" x-cloak><label class="label" for="specify">Specify</label><textarea id="specify" rows="2" class="field" maxlength="2000" x-model="form.adjustments.specify"></textarea></div>
    </div>
  </section>

  <section class="card p-6 sm:p-8">
    <p class="kicker">Declaration & sign-off</p><h2 class="mt-1 text-xl font-semibold">{{ $willComplete ? 'Final signature & completion' : 'Signature' }}</h2>
    <div class="mt-4"><label for="final-comments" class="label">General comments <span class="normal-case tracking-normal text-slate-400">(optional)</span></label><textarea id="final-comments" rows="2" class="field" x-model="comments"></textarea></div>
    <label class="mt-5 flex cursor-pointer items-center gap-3 text-sm font-medium"><input type="checkbox" class="h-5 w-5 rounded-md accent-[#006699]" x-model="consensus"> {{ $external ? 'Consensus reached with the examiner' : 'Consensus reached between the examiner and the moderator(s)' }}</label>
    <div class="mt-5"><x-signoff statement="By signing, I declare that consensus has been reached and the answers above are my honest assessment." /></div>
    <x-notice tone="info" class="mt-5">
      @if ($external) After your approval the examiner and the Head of Department sign Section 3, then the signed report is archived.
      @elseif ($willComplete) Approving completes moderation: the report is generated, archived and made available to the Head of Department.
      @else After your approval the record is forwarded to the external moderator. @endif
    </x-notice>
    <template x-if="error"><x-notice tone="error" class="mt-4"><span x-text="error"></span></x-notice></template>
    <div class="mt-6 flex flex-wrap justify-end gap-3">
      <button type="button" class="btn-secondary" @click="sheet = true"><x-icon name="reply" /> Return to examiner</button>
      <button type="button" class="btn-primary" @click="approve()" :disabled="!complete || !consensus || !sig || busy"><x-icon name="check-circle" /> <span x-text="busy === 'approve' ? 'Approving…' : 'Sign & approve'"></span></button>
    </div>
    <p x-show="!complete" class="mt-3 text-right text-xs text-slate-400">Answer every question and the adjustments question.</p>
  </section>

  <x-slide-over open="sheet" title="Return to examiner" subtitle="The examiner will correct Section 2 and resubmit; sign-offs restart from Gate 2.">
    <div class="space-y-4">
      <div><label class="label" for="ret">Feedback</label><textarea id="ret" rows="9" class="field" x-model="comments"></textarea></div>
      <template x-if="error"><x-notice tone="error"><span x-text="error"></span></x-notice></template>
      <button type="button" class="btn-primary w-full" :disabled="comments.trim().length < 5 || busy" @click="revise()">Send back</button>
    </div>
  </x-slide-over>
</div>
