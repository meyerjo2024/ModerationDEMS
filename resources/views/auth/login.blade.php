<x-bare-layout title="Sign in">
@php $inst = config('dems.institution'); @endphp
<main class="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
  <section class="relative hidden overflow-hidden bg-[var(--fog)] p-12 lg:flex lg:flex-col lg:justify-between">
    <div class="relative flex items-center gap-3">
      <span class="grid h-10 w-10 place-items-center rounded-lg bg-accent text-white"><x-icon name="shield" class="h-5 w-5" /></span>
      <div class="leading-tight"><p class="font-semibold">{{ $inst }}</p><p class="text-xs text-[var(--ink-3)]">Moderation DEMS</p></div>
    </div>
    <div class="relative max-w-lg">
      <p class="mb-4 inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-semibold text-[var(--ink-2)]"><x-icon name="sparkles" class="h-3.5 w-3.5 text-accent" /> Quality assurance, digitised</p>
      <h1 class="text-5xl font-semibold leading-[1.08]">Assessment moderation, <span class="text-accent dark:text-accent-dark">without the paperwork.</span></h1>
      <p class="mt-5 text-lg text-[var(--ink-2)]">From draft paper to signed, archived report — one secure workflow for examiners, moderators and heads of department.</p>
      <ul class="mt-10 space-y-5">
        @foreach ([['clipboard', 'Four-gate moderation', 'Pre-moderation, final review and optional external sign-off.'], ['pen', 'Authenticated signatures', 'Pen signature plus password, timestamped to your account.'], ['history', 'Audit-ready reports', 'A tamper-evident trail and a signed PDF for the HOD.']] as [$i, $t, $d])
          <li class="flex gap-4"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-white"><x-icon :name="$i" class="h-5 w-5 text-accent" /></span><span><span class="block font-semibold">{{ $t }}</span><span class="text-sm text-[var(--ink-2)]">{{ $d }}</span></span></li>
        @endforeach
      </ul>
    </div>
    <p class="relative text-xs text-[var(--ink-3)]">© {{ date('Y') }} {{ $inst }}</p>
  </section>

  <section class="flex items-center justify-center px-5 py-12">
    <div class="w-full max-w-md" x-data="{ email: @js(old('email', '')), password: '' }">
      <div class="mb-8 flex items-center gap-3 lg:hidden"><span class="grid h-10 w-10 place-items-center rounded-lg bg-accent text-white"><x-icon name="shield" class="h-5 w-5" /></span><p class="font-semibold">Moderation DEMS</p></div>
      <h2 class="text-3xl font-semibold">Welcome back</h2>
      <p class="mt-1 text-slate-500 dark:text-zinc-400">Sign in with your institutional account.</p>
      <form method="POST" action="{{ url('/login') }}" class="card mt-8 space-y-4 p-6 sm:p-8">
        @csrf
        <div><label class="label" for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="username" required class="field" placeholder="you@institution.edu" x-model="email"></div>
        <div><label class="label" for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required class="field" x-model="password"></div>
        @if (session('status'))<x-notice tone="success">{{ session('status') }}</x-notice>@endif
        @if (session('error'))<x-notice tone="error">{{ session('error') }}</x-notice>@endif
        @error('email')<x-notice tone="error">{{ $message }}</x-notice>@enderror
        <button type="submit" class="btn-primary w-full">Sign in <x-icon name="arrow-right" /></button>
      </form>
      <p class="mt-6 text-center text-sm text-[var(--ink-2)]">New member of staff? <a href="{{ route('register') }}" class="font-semibold text-accent dark:text-accent-dark">Register for an account</a></p>
    </div>
  </section>
</main>
</x-bare-layout>
