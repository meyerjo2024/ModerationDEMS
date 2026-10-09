{{-- Renders a .docx in the browser and lets the reviewer comment on selected passages. --}}
@props(['att'])
<div x-data="wordReview(@js(['url' => route('files.show', $att), 'comments' => url('/attachments/'.$att->id.'/comments')]))" class="space-y-3">
  <template x-if="loading"><p class="rounded-2xl bg-slate-900/[.04] px-4 py-10 text-center text-sm text-slate-500 dark:bg-white/5 dark:text-zinc-400">Opening document…</p></template>
  <template x-if="locked"><form @submit.prevent="unlock()" class="card-soft mx-auto grid max-w-md gap-3 px-6 py-8 text-center">
    <x-icon name="lock" class="mx-auto h-8 w-8 text-slate-400" />
    <div><p class="font-semibold">This document is password-protected</p><p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Enter the password the examiner gave you. It is used in your browser only and is never sent to the server or stored.</p></div>
    <input type="password" class="field text-center" autocomplete="off" placeholder="Document password" aria-label="Document password" x-model="password" required>
    <template x-if="error"><p class="text-sm font-medium text-rose-600" x-text="error"></p></template>
    <button class="btn-primary justify-center" :disabled="unlocking || !password"><span x-text="unlocking ? 'Unlocking…' : 'Unlock and review'"></span></button>
    <a href="{{ route('files.show', $att) }}" class="text-xs font-medium text-accent dark:text-accent-dark">Download the protected file instead</a>
  </form></template>
  <template x-if="error && !html && !locked"><div class="card-soft grid place-items-center gap-3 px-6 py-12 text-center"><x-icon name="file" class="h-9 w-9 text-slate-400" /><div><p class="font-medium">{{ $att->filename }}</p><p class="text-sm text-slate-500 dark:text-zinc-400" x-text="error"></p></div>
    <a href="{{ route('files.show', $att) }}" class="btn-primary"><x-icon name="download" /> Download to review</a></div></template>
  <div x-show="html" x-cloak class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
    <div>
      <p class="mb-2 text-xs text-slate-500 dark:text-zinc-400" x-show="can.comment">Select any text in the document to comment on it. Comments are saved immediately and go back to the examiner when you request a revision.</p>
      <div class="doc-body max-h-[70vh] overflow-auto rounded-2xl border border-slate-200/70 bg-white p-6 text-slate-900 sm:p-8 dark:border-white/10" x-ref="body" @mouseup="select()" @touchend="select()"></div>
    </div>
    <aside class="space-y-3">
      <div class="flex items-center justify-between"><p class="kicker">Comments · <span x-text="comments.length"></span></p><button type="button" class="text-xs font-medium text-accent dark:text-accent-dark" x-show="can.comment" @click="general()">+ General comment</button></div>
      <template x-if="draft"><div class="card-soft space-y-2 p-3">
        <p class="text-xs text-slate-500 dark:text-zinc-400" x-show="draft.quote">On: <span class="italic" x-text="'“' + (draft.quote || '').slice(0, 140) + '”'"></span></p>
        <textarea rows="3" class="field text-sm" maxlength="3000" placeholder="Your comment for the examiner…" x-model="text"></textarea>
        <div class="flex justify-end gap-2"><button type="button" class="btn-secondary !py-1.5 text-[13px]" @click="draft = null">Cancel</button><button type="button" class="btn-primary !py-1.5 text-[13px]" :disabled="text.trim().length < 2 || busy" @click="save()">Save comment</button></div>
      </div></template>
      <ul class="max-h-[58vh] space-y-2 overflow-auto">
        <template x-for="c in comments" :key="c.id"><li :id="'c-' + c.id" class="card-soft p-3 text-sm" :class="active === c.id ? 'ring-2 ring-amber-400' : ''">
          <button type="button" class="block w-full text-left text-xs italic text-slate-500 dark:text-zinc-400" x-show="c.quote" @click="goTo(c)"><span x-text="c.quote ? '“' + c.quote.slice(0, 110) + (c.quote.length > 110 ? '…' : '') + '”' : ''"></span><span x-show="!c.current" class="ml-1 not-italic">(earlier version)</span></button>
          <p class="mt-1 whitespace-pre-wrap" x-text="c.body"></p>
          <p class="mt-1 text-[11px] text-slate-400"><span x-text="c.author"></span> · <span x-text="c.at"></span></p>
          <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400" x-show="c.addressed">✓ Addressed by the examiner</p>
          <div class="mt-2 flex gap-3 text-xs font-medium"><button type="button" x-show="can.address" class="text-accent dark:text-accent-dark" @click="toggle(c)" x-text="c.addressed ? 'Mark as open' : 'Mark as addressed'"></button><button type="button" x-show="can.comment && c.mine" class="text-rose-600" @click="remove(c)">Delete</button></div>
        </li></template>
        <template x-if="!comments.length"><li class="text-xs text-slate-400">No comments yet.</li></template>
      </ul>
    </aside>
  </div>
</div>
