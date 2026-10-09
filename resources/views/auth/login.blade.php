<x-bare-layout title="Sign in">
@php $inst = config('dems.institution'); @endphp
<main class="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
  <section class="relative hidden overflow-hidden bg-navy p-12 text-white lg:flex lg:flex-col lg:justify-between">
    <div class="pointer-events-none absolute -right-32 -top-32 h-[28rem] w-[28rem] rounded-full bg-brand/25 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-40 -left-24 h-[30rem] w-[30rem] rounded-full bg-accent/50 blur-3xl"></div>
    <div class="pointer-events-none absolute inset-0 opacity-[.07]" style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:22px 22px"></div>
    <div class="relative flex items-center gap-3">
      <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand shadow-lift"><x-icon name="shield" class="h-5 w-5" /></span>
      <div class="leading-tight"><p class="font-semibold">{{ $inst }}</p><p class="text-xs uppercase tracking-[.16em] text-white/60">Moderation DEMS</p></div>
    </div>
    <div class="relative max-w-lg">
      <p class="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white/90 backdrop-blur"><x-icon name="sparkles" class="h-3.5 w-3.5 text-brand" /> Quality assurance, digitised</p>
      <h1 class="text-5xl font-semibold leading-[1.05] tracking-tight">Assessment moderation, <span class="text-brand">without the paperwork.</span></h1>
      <p class="mt-5 text-lg text-white/70">From draft paper to signed, archived report — one secure workflow for examiners, moderators and heads of department.</p>
      <ul class="mt-10 space-y-5">
        @foreach ([['clipboard', 'Four-gate moderation', 'Pre-moderation, final review and optional external sign-off.'], ['pen', 'Authenticated signatures', 'Pen signature plus password, timestamped to your account.'], ['history', 'Audit-ready reports', 'A tamper-evident trail and a signed PDF for the HOD.']] as [$i, $t, $d])
          <li class="flex gap-4"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/10 backdrop-blur"><x-icon :name="$i" class="h-5 w-5 text-brand" /></span><span><span class="block font-semibold">{{ $t }}</span><span class="text-sm text-white/65">{{ $d }}</span></span></li>
        @endforeach
      </ul>
    </div>
    <p class="relative text-xs text-white/40">© {{ date('Y') }} {{ $inst }}</p>
  </section>

  <section class="flex items-center justify-center px-5 py-12">
    <div class="w-full max-w-md" x-data="{ email: @js(old('email', '')), password: '' }">
      <div class="mb-8 flex items-center gap-3 lg:hidden"><span class="grid h-10 w-10 place-items-center rounded-xl bg-brand text-white"><x-icon name="shield" class="h-5 w-5" /></span><p class="font-semibold">Moderation DEMS</p></div>
      <h2 class="text-3xl font-semibold">Welcome back</h2>
      <p class="mt-1 text-slate-500 dark:text-zinc-400">Sign in with your institutional account.</p>
      <form method="POST" action="{{ url('/login') }}" class="card mt-8 space-y-4 p-6 sm:p-8">
        @csrf
        <div><label class="label" for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="username" required class="field" placeholder="you@institution.edu" x-model="email"></div>
        <div><label class="label" for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required class="field" x-model="password"></div>
        @if (session('error'))<x-notice tone="error">{{ session('error') }}</x-notice>@endif
        @error('email')<x-notice tone="error">{{ $message }}</x-notice>@enderror
        <button type="submit" class="btn-brand w-full">Sign in <x-icon name="arrow-right" /></button>
      </form>
      @if ($demo)
        <div class="mt-6 rounded-3xl border border-brand/30 bg-brand-soft/70 p-5 dark:bg-brand/10">
          <p class="kicker !text-brand">Demo accounts · click to fill</p>
          <ul class="mt-3 space-y-1.5">
            @foreach ($demo['accounts'] as $acc)
              <li><button type="button" @click="email = @js($acc['email']); password = @js($demo['password'])" class="flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2 text-left text-sm transition hover:bg-white/80 dark:hover:bg-white/10"><span class="font-medium">{{ $acc['role'] }}</span><span class="truncate text-slate-500 dark:text-zinc-400">{{ $acc['email'] }}</span></button></li>
            @endforeach
          </ul>
          <p class="mt-3 border-t border-brand/20 pt-3 text-xs text-slate-600 dark:text-zinc-300">Password for all demo accounts: <code class="rounded bg-white/80 px-1.5 py-0.5 font-mono font-semibold dark:bg-white/10">{{ $demo['password'] }}</code></p>
        </div>
      @endif
    </div>
  </section>
</main>
</x-bare-layout>
