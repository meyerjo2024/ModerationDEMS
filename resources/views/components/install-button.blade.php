{{-- "Install app" for the browser. Shows only where installing is possible and the app is not already installed.
     variant: header (compact) | block (full-width button, e.g. on the sign-in page) --}}
@props(['variant' => 'header'])
<div x-data="installApp" x-show="show" x-cloak class="{{ $variant === 'block' ? 'mt-4' : '' }}">
  @if ($variant === 'block')
    <button type="button" @click="install()" class="btn-secondary w-full"><x-icon name="download" /> Install the app on this device</button>
  @else
    <button type="button" @click="install()" title="Install the app for quick access" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-[13px] font-medium text-[#303233] hover:bg-[var(--fog)] dark:text-zinc-300"><x-icon name="download" class="h-[18px] w-[18px]" /><span class="hidden md:inline">Install app</span></button>
  @endif
  <div x-show="help" x-cloak class="fixed inset-0 z-50 grid place-items-end bg-black/40 p-4 sm:place-items-center" @click.self="help = false" @keydown.escape.window="help = false" role="dialog" aria-modal="true" aria-label="Install the app">
    <div class="card w-full max-w-sm space-y-3 !bg-[var(--canvas)] p-6 text-sm shadow-lift">
      <p class="text-lg font-semibold">Add to your Home Screen</p>
      <ol class="list-decimal space-y-2 pl-5 text-[var(--ink-2)]">
        <li>Tap the <b>Share</b> button <span aria-hidden="true">⬆︎</span> in Safari (at the bottom or top of the screen).</li>
        <li>Scroll down and tap <b>Add to Home Screen</b>.</li>
        <li>Tap <b>Add</b>. The Moderation icon now opens the platform full-screen, like an app.</li>
      </ol>
      <p class="text-xs text-[var(--ink-3)]">On a Mac, use Safari → File → <b>Add to Dock</b>. In Chrome or Edge, use the install icon in the address bar.</p>
      <button type="button" class="btn-primary w-full" @click="help = false">Got it</button>
    </div>
  </div>
</div>
