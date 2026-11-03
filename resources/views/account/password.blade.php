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
</div>
</x-app-layout>
