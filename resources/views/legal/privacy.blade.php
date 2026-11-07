<x-bare-layout title="Privacy">
@php $inst = config('dems.institution'); $mail = config('dems.support_email'); @endphp
<main class="min-h-screen bg-[var(--fog)] px-4 py-12"><div class="mx-auto max-w-3xl">
  <a href="{{ url('/') }}" class="text-sm font-medium text-accent">← Back</a>
  <h1 class="mt-3 text-3xl font-semibold">Privacy notice</h1>
  <p class="mt-1 text-sm text-[var(--ink-3)]">Moderation DEMS · {{ $inst }}</p>
  <div class="card mt-6 space-y-5 p-6 text-[15px] leading-relaxed sm:p-8">
    <p>This notice explains what the Digital Examination Moderation System (the “system”) records about you and why. It is written for staff of {{ $inst }} and external moderators. <b>[Draft: to be reviewed by the University’s legal / information officer before publication.]</b></p>
    <section><h2 class="text-lg font-semibold">What we record</h2><ul class="mt-2 list-disc space-y-1 pl-5">
      <li><b>Your account:</b> name, e-mail address, department, role(s) and the subjects you are responsible for.</li>
      <li><b>Your actions:</b> drawn signatures, comments on papers, ratings and answers on the moderation forms, and a tamper-evident audit trail with the time and your network (IP) address.</li>
      <li><b>Documents you upload:</b> question papers, memoranda, marks sheets and sample scripts. <b>Student names and student numbers are never read or stored</b>; only an anonymous column of marks is used.</li>
      <li><b>Phone app:</b> if you use the iPhone app and allow notifications, a device token so reminders can reach your phone. It contains no personal details.</li>
    </ul></section>
    <section><h2 class="text-lg font-semibold">Why</h2><p class="mt-2">To run and document the moderation of assessments, to meet quality-assurance requirements, to send reminders about your deadlines, and to keep the system secure.</p></section>
    <section><h2 class="text-lg font-semibold">Who can see it</h2><p class="mt-2">Only the people involved in an assessment (its examiner, internal moderator, the Head of Department of the subject and, once it reaches them, the external moderator) can see its record. Administrators (Heads of Department) manage accounts. We do not sell or share your data for advertising, and the app contains no advertising or tracking.</p></section>
    <section><h2 class="text-lg font-semibold">Where it is kept and for how long</h2><p class="mt-2">Data is stored with our hosting and database providers (Render and Supabase, [region to be confirmed]) over encrypted connections. Completed moderation records, signatures and the audit trail are kept as academic quality records for [retention period to be confirmed by the University].</p></section>
    <section><h2 class="text-lg font-semibold">Your rights</h2><p class="mt-2">You may ask what we hold about you, ask for corrections, and delete your account at any time in the app (<b>Your name → Delete my account</b>). Deleting your account removes your name, e-mail and settings; signed records of assessments you took part in are kept without your personal details (shown as “Former user”) because they are quality records.</p></section>
    <section><h2 class="text-lg font-semibold">Contact</h2><p class="mt-2">Questions or requests: {{ $mail ?: '[support e-mail to be added]' }}. You also have the right to complain to the Information Regulator (South Africa).</p></section>
  </div>
</div></main>
</x-bare-layout>
