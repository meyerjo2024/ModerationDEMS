<x-bare-layout title="Support">
@php $mail = config('dems.support_email'); $tel = config('dems.support_phone'); @endphp
<main class="min-h-screen bg-[var(--fog)] px-4 py-12"><div class="mx-auto max-w-2xl">
  <a href="{{ url('/') }}" class="text-sm font-medium text-accent">← Back</a>
  <h1 class="mt-3 text-3xl font-semibold">Support</h1>
  <div class="card mt-6 space-y-5 p-6 text-[15px] leading-relaxed sm:p-8">
    <section><h2 class="text-lg font-semibold">Contact</h2><p class="mt-2">E-mail: {{ $mail ?: '[support e-mail to be added]' }}@if ($tel)<br>Phone: {{ $tel }}@endif</p></section>
    <section><h2 class="text-lg font-semibold">Common questions</h2><ul class="mt-2 list-disc space-y-2 pl-5">
      <li><b>I cannot sign in.</b> New staff must register and wait for the Head of Department to approve the account. Use the e-mail you registered with. You can change your password after signing in.</li>
      <li><b>I forgot my password.</b> Ask the Head of Department to reset it.</li>
      <li><b>I am not offered a subject or moderator.</b> Tick your subjects under <i>My subjects</i>; moderators must register as Internal Moderator first.</li>
      <li><b>Install on iPhone.</b> Safari → Share → Add to Home Screen, or install the App Store app.</li>
    </ul></section>
  </div>
</div></main>
</x-bare-layout>
