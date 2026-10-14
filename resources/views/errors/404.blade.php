<x-app-layout title="Not found">
<div class="mx-auto max-w-xl space-y-5 py-16 text-center">
  <p class="kicker">Error 404</p>
  <h1 class="text-4xl">We can’t show that page</h1>
  @auth
    <p class="text-[var(--ink-2)]">Either the record does not exist, or it is not visible to <b>{{ auth()->user()->name }}</b> ({{ auth()->user()->roleLabels() }}). A record is only visible to its examiner, its internal moderator, the Head of Department of its subject and — once it has reached them — the external moderator.</p>
    <p class="text-sm text-[var(--ink-3)]">Signed in as {{ auth()->user()->email }}. If you expected to see it, sign out and sign in with the account that was assigned to it.</p>
    <div class="flex justify-center gap-3"><a href="{{ route('dashboard') }}" class="btn-primary">Go to my dashboard</a>
      <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-secondary">Sign out</button></form></div>
  @else
    <p class="text-[var(--ink-2)]">Please sign in to continue.</p>
    <a href="{{ route('login') }}" class="btn-primary">Sign in</a>
  @endauth
</div>
</x-app-layout>
