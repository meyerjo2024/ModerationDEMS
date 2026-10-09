@php
$SG = \App\Enums\SignatureSection::class;
$a->loadMissing(['records', 'signatures']);
$caps = array_values(array_filter([$a->examiner_id === $user->id ? $SG::ExaminerSection3 : null, $a->subject->hod_id === $user->id ? $SG::HodSection3 : null]));
$open = array_values(array_filter($caps, fn ($c) => ! $a->hasSection3Signature($c)));
$mine = $caps[0] ?? null;
$signed = $caps && ! $open;
$people = [['Examiner', $a->examiner->name, $a->hasSection3Signature($SG::ExaminerSection3)], ['Head of Department', $a->subject->hod->name, $a->hasSection3Signature($SG::HodSection3)]];
@endphp
<div x-data="section3(@js(['id' => $a->id, 'open' => array_map(fn ($c) => $c->value, $open)]))" @signed="sig = $event.detail" class="space-y-6">
  <section class="card p-6 sm:p-8">
    <p class="kicker">Section 3 · Sign-off</p><h2 class="mt-1 text-2xl font-semibold">External moderator’s report</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">The external moderator has completed Section 3. The examiner and the Head of Department sign to acknowledge it; the signed report is then archived.</p>
    <ul class="mt-5 grid gap-3 sm:grid-cols-2">
      @foreach ($people as [$role, $name, $done])
        <li class="card-soft flex items-center justify-between gap-3 px-4 py-3 text-sm"><span><span class="kicker block">{{ $role }}</span><span class="font-medium">{{ $name }}</span></span>
          <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $done ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500/10 text-amber-700 dark:text-amber-300' }}"><x-icon :name="$done ? 'check' : 'hourglass'" class="h-3.5 w-3.5" /> {{ $done ? 'Signed' : 'Waiting' }}</span></li>
      @endforeach
    </ul>
  </section>

  @include('assessments.partials.form-readonly', ['a' => $a, 'sections' => ['s3', 's2', 's1']])

  @if ($mine && ! $signed)
    <section class="card p-6 sm:p-8">
      <p class="kicker">Your signature</p><h2 class="mt-1 text-xl font-semibold">Sign Section 3 as {{ $open[0] === $SG::HodSection3 ? 'Head of Department' : 'Examiner' }}</h2>
      @if (count($open) > 1)<p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">You hold both roles on this record, so you sign twice — once as Examiner and once as Head of Department. This is the first.</p>@endif
      <div class="mt-5"><x-signoff statement="By signing, I confirm I have read the external moderator’s comments in Section 3." /></div>
      <template x-if="error"><x-notice tone="error" class="mt-5"><span x-text="error"></span></x-notice></template>
      <div class="mt-6 flex justify-end"><button type="button" class="btn-primary" @click="sign()" :disabled="!sig || busy"><x-icon name="pen" /> <span x-text="busy === 'sign' ? 'Signing…' : 'Sign Section 3'"></span></button></div>
    </section>
  @elseif ($mine)
    <x-notice tone="success">You have signed Section 3. The record completes as soon as the other signature is in.</x-notice>
  @endif
</div>
