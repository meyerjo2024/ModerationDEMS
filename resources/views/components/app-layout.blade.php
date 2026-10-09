@props(['title' => 'Moderation DEMS', 'wide' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#0a1f44">
  <title>{{ $title }} · Moderation DEMS</title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
  @auth
    @php $u = auth()->user(); $link = 'whitespace-nowrap rounded-full px-3 py-1.5 hover:bg-white/10 hover:text-white'; @endphp
    <header class="glass-header">
      <div class="mx-auto flex h-14 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6">
        <div class="flex items-center gap-6">
          <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 font-semibold tracking-tight">
            <span class="grid h-7 w-7 place-items-center rounded-lg bg-brand text-white shadow-sm"><x-icon name="shield" /></span><span class="hidden sm:inline">Moderation DEMS</span>
          </a>
          <nav class="flex items-center gap-1 text-sm font-medium text-white/80">
            <a href="{{ route('dashboard') }}" class="{{ $link }}">Dashboard</a>
            @if ($u->hasRole(\App\Enums\Role::Examiner))<a href="{{ route('assessments.create') }}" class="{{ $link }}">New assessment</a>@endif
            @if ($u->hasRole(\App\Enums\Role::Hod))<a href="{{ route('admin') }}" class="{{ $link }}">Admin</a>@endif
          </nav>
        </div>
        <div class="flex items-center gap-2">
          <div class="relative" x-data="notifs" @click.outside="open = false">
            <button type="button" @click="toggle()" class="relative rounded-full p-2 text-white/80 hover:bg-white/10" :aria-label="'Notifications' + (unread ? ' (' + unread + ' unread)' : '')">
              <x-icon name="bell" class="h-[18px] w-[18px]" /><span x-show="unread > 0" x-cloak class="absolute right-1 top-1 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-navy"></span>
            </button>
            <div x-show="open" x-cloak x-transition class="card absolute right-0 mt-2 w-80 overflow-hidden !bg-white/95 p-1.5 text-slate-900 shadow-lift dark:!bg-zinc-900/95 dark:text-zinc-100">
              <p class="kicker px-3 pb-1 pt-2">Notifications</p>
              <p x-show="!items.length" class="px-3 py-6 text-sm text-slate-500">You’re all caught up.</p>
              <ul class="max-h-80 overflow-y-auto"><template x-for="n in items" :key="n.id"><li>
                <a :href="n.url" class="block rounded-xl px-3 py-2.5 hover:bg-slate-900/5 dark:hover:bg-white/10"><p class="text-sm" :class="n.read ? 'text-slate-500 dark:text-zinc-400' : 'font-medium'" x-text="n.message"></p><p class="mt-0.5 text-xs text-slate-400" x-text="n.at"></p></a>
              </li></template></ul>
            </div>
          </div>
          <div class="hidden text-right leading-tight sm:block"><p class="text-[13px] font-semibold">{{ $u->name }}</p><p class="text-[11px] text-white/60">{{ $u->roleLabels() }}</p></div>
          <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-full p-2 text-white/80 hover:bg-white/10" aria-label="Sign out"><x-icon name="logout" class="h-[18px] w-[18px]" /></button></form>
        </div>
      </div>
    </header>
  @endauth
  <main class="mx-auto w-full px-4 pb-24 pt-8 sm:px-6 {{ $wide ? 'max-w-7xl' : 'max-w-6xl' }}">
    @if (session('status'))<x-notice tone="success" class="mb-6">{{ session('status') }}</x-notice>@endif
    @if (session('error'))<x-notice tone="error" class="mb-6">{{ session('error') }}</x-notice>@endif
    {{ $slot }}
  </main>
</body>
</html>
