<x-bare-layout title="Register">
@php $inst = config('dems.institution'); @endphp
<main class="min-h-screen bg-[var(--fog)] px-4 py-12">
  <div class="mx-auto max-w-xl">
    <div class="mb-8 flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-lg bg-accent text-white"><x-icon name="shield" class="h-5 w-5" /></span>
      <div class="leading-tight"><p class="font-semibold">{{ $inst }}</p><p class="text-xs text-[var(--ink-3)]">Moderation DEMS</p></div></div>
    <h1 class="text-3xl font-semibold">Register for an account</h1>
    <p class="mt-1 text-[var(--ink-2)]">For staff. Choose the role(s) you need; the Head of Department reviews your request before you can sign in.</p>
    <form method="POST" action="{{ route('register') }}" class="card mt-8 space-y-5 p-6 sm:p-8">
      @csrf
      @if ($errors->any())<x-notice tone="error">{{ $errors->first() }}</x-notice>@endif
      <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="name">Full name</label><input id="name" name="name" required maxlength="120" autocomplete="name" class="field" value="{{ old('name') }}"></div>
        <div><label class="label" for="email">E-mail</label><input id="email" name="email" type="email" required autocomplete="username" class="field" value="{{ old('email') }}" placeholder="you@institution.edu"></div>
        <div class="sm:col-span-2"><label class="label" for="department">Department <span class="font-normal">(optional)</span></label><input id="department" name="department" maxlength="120" class="field" value="{{ old('department') }}"></div>
      </div>
      <fieldset><legend class="label">I need to act as</legend>
        <div class="mt-1 space-y-2 text-sm">
          @foreach ($roles as $r)
            <label class="flex cursor-pointer items-start gap-2.5"><input type="checkbox" name="roles[]" value="{{ $r->value }}" class="mt-0.5 h-4 w-4 rounded accent-[#006699]" @checked(in_array($r->value, old('roles', []), true))>
              <span><b>{{ $r->label() }}</b> <span class="text-[var(--ink-3)]">— {{ ['EXAMINER' => 'sets assessments and uploads papers and marks', 'INTERNAL_MODERATOR' => 'moderates assessments within the department', 'EXTERNAL_MODERATOR' => 'gives the independent external review'][$r->value] }}</span></span></label>
          @endforeach
        </div>
        <p class="mt-2 text-xs text-[var(--ink-3)]">You can hold more than one role. Head of Department access is granted by an administrator.</p>
      </fieldset>
      <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="password">Password</label><input id="password" name="password" type="password" required minlength="10" autocomplete="new-password" class="field"><p class="mt-1 text-xs text-[var(--ink-3)]">At least 10 characters.</p></div>
        <div><label class="label" for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="field"></div>
      </div>
      <button class="btn-primary w-full">Request an account</button>
    </form>
    <p class="mt-6 text-center text-sm text-[var(--ink-2)]">Already registered? <a href="{{ route('login') }}" class="font-semibold text-accent dark:text-accent-dark">Sign in</a></p>
  </div>
</main>
</x-bare-layout>
