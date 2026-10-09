@php $AK = \App\Enums\AttachmentKind::class; @endphp
@php $AK = \App\Enums\AttachmentKind::class; @endphp
<div x-data="preReview(@js(['id' => $a->id]))" @signed="sig = $event.detail" class="space-y-6">
  <section class="card p-6 sm:p-8">
    <p class="kicker">Gate 1 · Review</p><h2 class="mt-1 text-2xl font-semibold">Assessment paper & memorandum</h2>
    <p class="mb-5 mt-1 text-sm text-slate-500 dark:text-zinc-400">Check alignment with the level descriptors, then approve or send it back with feedback.</p>
    <x-doc-viewer :docs="[['Assessment paper', $a->latestAttachment($AK::Paper)], ['Memorandum', $a->latestAttachment($AK::Memo)]]" />
  </section>
  <section class="card p-6 sm:p-8"><p class="kicker">Section 1</p><h2 class="mb-4 mt-1 text-xl font-semibold">Examiner’s question types</h2><x-question-table :rows="$a->question_types ?? []" /></section>
  <section class="card p-6 sm:p-8">
    <p class="kicker">Decision</p>
    <div class="mt-3 flex flex-wrap gap-3">
      <button type="button" class="btn-secondary" @click="sheet = true"><x-icon name="reply" /> Request revision</button>
      <button type="button" class="btn-primary" @click="approving = !approving"><x-icon name="check-circle" /> Approve…</button>
    </div>
    <div x-show="approving" x-cloak x-transition class="mt-6 space-y-5 border-t border-slate-200/60 pt-6 dark:border-white/10">
      <div><label class="label" for="pre-comments">Comments <span class="normal-case tracking-normal text-slate-400">(optional)</span></label><textarea id="pre-comments" rows="3" class="field" x-model="comments"></textarea></div>
      <label class="flex cursor-pointer items-center gap-3 text-sm font-medium"><input type="checkbox" class="h-5 w-5 rounded-md accent-[#0b4ea2]" x-model="consensus"> Consensus reached with the examiner</label>
      <x-signoff statement="By signing, I confirm I have reviewed the paper and memorandum and approve them for assessment." />
      <template x-if="error"><x-notice tone="error"><span x-text="error"></span></x-notice></template>
      <div class="flex justify-end"><button type="button" class="btn-primary" :disabled="!consensus || !sig || busy" @click="approve()"><span x-text="busy === 'approve' ? 'Approving…' : 'Sign & approve'"></span></button></div>
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
