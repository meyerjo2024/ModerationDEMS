{{-- Pen signature + password re-confirmation. Emits a bubbling "signed" event ({image,password}|null). --}}
@props(['statement'])
<div x-data="signoff" class="space-y-4">
  <p class="flex items-start gap-2 text-sm text-slate-600 dark:text-zinc-300"><x-icon name="shield" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />{{ $statement }}</p>
  <div class="relative">
    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-inner dark:border-white/15">
      <canvas x-ref="pad" style="height:150px;touch-action:none" class="block w-full cursor-crosshair" aria-label="Signature pad — draw your signature"
        @pointerdown="down($event)" @pointermove="move($event)" @pointerup="up()" @pointercancel="up()"></canvas>
      <span x-show="empty" class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-slate-300">Sign here</span>
      <span class="pointer-events-none absolute inset-x-6 bottom-9 border-b border-dashed border-slate-200"></span>
    </div>
    <button type="button" @click="clear()" class="absolute right-3 top-3 inline-flex items-center gap-1 rounded-full bg-slate-900/5 px-2.5 py-1 text-xs font-medium text-slate-500 hover:bg-slate-900/10"><x-icon name="eraser" class="h-3.5 w-3.5" /> Clear</button>
  </div>
  <div>
    <label class="label" for="sign-password">Confirm with your password</label>
    <div class="relative">
      <x-icon name="lock" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
      <input id="sign-password" type="password" autocomplete="current-password" class="field pl-10" placeholder="Your account password" x-model="password" @input="emit()">
    </div>
    <p class="mt-1.5 text-xs text-slate-400">Your signature is timestamped and bound to your authenticated account.</p>
  </div>
</div>
