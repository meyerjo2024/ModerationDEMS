<x-app-layout title="Change password">
<div class="mx-auto max-w-lg space-y-6">
  <div><p class="kicker">My account</p><h1 class="mt-1 text-3xl font-semibold">Change password</h1>
    <p class="mt-1 text-sm text-[var(--ink-2)]">Signed in as <b>{{ auth()->user()->name }}</b> ({{ auth()->user()->email }}).</p></div>
  <form method="POST" action="{{ route('account.password.update') }}" class="card space-y-5 p-6 sm:p-8">
    @csrf
    @if ($errors->any())<x-notice tone="error">{{ $errors->first() }}</x-notice>@endif
    <div><label class="label" for="current_password">Current password</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="field"></div>
    <div><label class="label" for="password">New password</label><input id="password" name="password" type="password" required minlength="10" autocomplete="new-password" class="field"><p class="mt-1 text-xs text-[var(--ink-3)]">At least 10 characters, different from your current password.</p></div>
    <div><label class="label" for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="field"></div>
    <div class="flex justify-end gap-3"><a href="{{ route('dashboard') }}" class="btn-secondary">Cancel</a><button class="btn-primary">Change password</button></div>
  </form>
  <section class="card space-y-3 p-6 sm:p-8" x-data="installApp" x-show="show" x-cloak>
    <div><p class="kicker">This device</p><h2 class="mt-1 text-xl font-semibold">Install the app</h2>
      <p class="mt-1 text-sm text-[var(--ink-2)]">Put Moderation on your home screen or desktop for one-tap access. It opens full-screen and stays signed in like any app.</p></div>
    <button type="button" class="btn-secondary" @click="install()"><x-icon name="download" /> Install app</button>
    <div x-show="help" x-cloak class="rounded-xl bg-[var(--fog)] p-4 text-sm text-[var(--ink-2)]">Tap <b>Share</b> ⬆︎ in Safari, then <b>Add to Home Screen</b>, then <b>Add</b>.</div>
  </section>
  <form method="POST" action="{{ route('account.notifications') }}" class="card space-y-3 p-6 sm:p-8">@csrf
    <div><p class="kicker">Notifications</p><h2 class="mt-1 text-xl font-semibold">E-mail</h2>
      <p class="mt-1 text-sm text-[var(--ink-2)]">Get an e-mail when something is waiting on you and when a deadline or test date is close. You always see these under the bell in the platform.</p></div>
    <input type="hidden" name="email_notifications" value="0">
    <label class="flex items-center gap-3 text-sm font-medium"><input type="checkbox" name="email_notifications" value="1" class="h-5 w-5 rounded-md accent-[#006699]" @checked(auth()->user()->email_notifications)> Send me e-mails</label>
    <div class="flex justify-end"><button class="btn-secondary">Save</button></div>
  </form>
  <section x-data="faceId" x-show="native" x-cloak class="card space-y-3 p-6 sm:p-8">
    <div><p class="kicker">iPhone app</p><h2 class="mt-1 text-xl font-semibold">Face ID lock</h2>
      <p class="mt-1 text-sm text-[var(--ink-2)]">Ask for Face ID when the app opens and when you come back to it after a minute away. You stay signed in; this only locks the screen.</p></div>
    <label class="flex items-center gap-3 text-sm font-medium"><input type="checkbox" class="h-5 w-5 rounded-md accent-[#006699]" :checked="on" :disabled="!available" @click.prevent="toggle()"> Lock the app with Face ID</label>
    <p class="text-sm text-[var(--ink-2)]" x-show="msg" x-text="msg"></p>
  </section>
  <form method="POST" action="{{ route('account.destroy') }}" class="card space-y-4 border-rose-300 p-6 sm:p-8" onsubmit="return confirm('Delete your account permanently? This cannot be undone.')">
    @csrf @method('DELETE')
    <div><p class="kicker">Danger zone</p><h2 class="mt-1 text-xl font-semibold">Delete my account</h2>
      <p class="mt-1 text-sm text-[var(--ink-2)]">Removes your name, e-mail, subjects and settings and signs you out for good. Signed records you took part in are kept without your personal details (shown as “Former user”). You cannot delete your account while you are named on assessments that are not finished.</p></div>
    <div><label class="label" for="del_pw">Current password</label><input id="del_pw" name="current_password" type="password" required autocomplete="current-password" class="field"></div>
    <label class="flex items-start gap-2.5 text-sm"><input type="checkbox" name="confirm_delete" value="1" class="mt-0.5 h-4 w-4 rounded accent-[#006699]"> I understand that this cannot be undone.</label>
    <div class="flex justify-end"><button class="btn-danger">Delete my account</button></div>
  </form>
</div>
</x-app-layout>
