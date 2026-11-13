{{-- Shows a PDF page by page in the browser; the reviewer selects text, or a whole page, to comment on it. --}}
@props(['att'])
<div x-data="pdfReview(@js(['url' => route('files.show', $att).'?inline=1', 'comments' => url('/attachments/'.$att->id.'/comments')]))" class="space-y-3">
  <template x-if="loading"><p class="rounded-2xl bg-slate-900/[.04] px-4 py-10 text-center text-sm text-slate-500 dark:bg-white/5 dark:text-zinc-400">Opening PDF…</p></template>
  <template x-if="error && !pages"><div class="card-soft grid place-items-center gap-3 px-6 py-12 text-center"><x-icon name="file" class="h-9 w-9 text-slate-400" /><div><p class="font-medium">{{ $att->filename }}</p><p class="text-sm text-slate-500 dark:text-zinc-400" x-text="error"></p></div>
    <a href="{{ route('files.show', $att) }}" class="btn-primary"><x-icon name="download" /> Download to review</a></div></template>
  <div x-show="pages" x-cloak class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
    <div>
      <p class="mb-2 text-xs text-slate-500 dark:text-zinc-400" x-show="can.comment">Select any text on a page to comment on it, or use “+ Comment on page”. Comments are saved immediately and go back to the examiner when you request a revision.</p>
      <div class="pdf-pages max-h-[75vh] space-y-4 overflow-auto rounded-2xl border border-slate-200/70 bg-slate-100 p-3 dark:border-white/10 dark:bg-zinc-800" @mouseup="select()" @touchend="select()"></div>
    </div>
    <aside class="space-y-3">
      <div class="flex items-center justify-between"><p class="kicker">Comments · <span x-text="comments.length"></span></p><button type="button" class="text-xs font-medium text-accent dark:text-accent-dark" x-show="can.comment" @click="general()">+ General comment</button></div>
      <template x-if="draft"><div class="card-soft space-y-2 p-3">
        <p class="text-xs text-slate-500 dark:text-zinc-400" x-show="draft.page">Page <span x-text="draft.page"></span><span x-show="draft.quote"> · on: <span class="italic" x-text="'“' + (draft.quote || '').slice(0, 140) + '”'"></span></span></p>
        <textarea rows="3" class="field text-sm" maxlength="3000" placeholder="Your comment for the examiner…" x-model="text"></textarea>
        <div class="flex justify-end gap-2"><button type="button" class="btn-secondary !py-1.5 text-[13px]" @click="draft = null">Cancel</button><button type="button" class="btn-primary !py-1.5 text-[13px]" :disabled="text.trim().length < 2 || busy" @click="save()">Save comment</button></div>
      </div></template>
      <ul class="max-h-[58vh] space-y-2 overflow-auto">
        <template x-for="c in comments" :key="c.id"><li :id="'pc-' + c.id" class="card-soft p-3 text-sm" :class="active === c.id ? 'ring-2 ring-amber-400' : ''">
          <button type="button" class="block w-full text-left text-xs text-slate-500 dark:text-zinc-400" x-show="c.quote || c.page" @click="goTo(c)"><span class="font-semibold not-italic" x-show="c.page" x-text="'Page ' + c.page"></span><span class="italic" x-show="c.quote" x-text="(c.page ? ' · ' : '') + '“' + (c.quote || '').slice(0, 110) + ((c.quote || '').length > 110 ? '…' : '') + '”'"></span><span x-show="!c.current" class="ml-1 not-italic">(earlier version)</span></button>
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
