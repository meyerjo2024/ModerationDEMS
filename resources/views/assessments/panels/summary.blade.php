@php $AK = \App\Enums\AttachmentKind::class; $S = \App\Enums\AssessmentStatus::class;


$report = $a->latestAttachment($AK::FinalReport);
$emailed = $a->auditLogs->contains('action', 'REPORT_EMAILED');
$waitingOn = ['examiner' => $a->examiner->name, 'internal' => $a->internalModerator->name, 'external' => $a->externalModerator?->name ?? 'the external moderator'][$a->status->actor()] ?? '';
$rows = $a->question_types ?? [];
$samples = $a->attachmentsOf($AK::SampleScript);
$isExaminer = $a->examiner_id === $user->id;
@endphp
<div class="space-y-6">
  @if ($a->status === $S::Completed)
    <section class="card flex flex-wrap items-center justify-between gap-4 p-6 sm:p-8">
      <div class="flex items-center gap-4"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"><x-icon name="party" class="h-6 w-6" /></span>
        <div><h2 class="text-xl font-semibold">Moderation complete</h2><p class="text-sm text-slate-500 dark:text-zinc-400">The signed report is archived{{ $emailed ? ' and was e-mailed to '.$a->subject->hod->name : '. E-mail to the Head of Department has not been delivered — download the PDF' }}.</p></div></div>
      @if ($report)<a href="{{ route('files.show', $report) }}" class="btn-primary"><x-icon name="download" /> Download PDF</a>@endif
    </section>
  @else
    <section class="card flex items-center gap-4 p-6 sm:p-8"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-amber-500/10 text-amber-600"><x-icon name="hourglass" class="h-6 w-6" /></span>
      <div><h2 class="text-xl font-semibold">Waiting on {{ $waitingOn }}</h2><p class="text-sm text-slate-500 dark:text-zinc-400">Next step: {{ $a->status->nextStep() }}.</p></div></section>
  @endif

  @if ($rows)
    <section class="card p-6 sm:p-8"><p class="kicker">Section 1</p><h2 class="mb-4 mt-1 text-xl font-semibold">Types of questions</h2><x-question-table :rows="$rows" /></section>
  @endif
  @if ($a->candidate_count !== null)
    <section class="card p-6 sm:p-8"><p class="kicker">Section 2</p><h2 class="mb-4 mt-1 text-xl font-semibold">Performance statistics</h2>
      @include('assessments.partials.stats', ['a' => $a])
      @if ($a->marks_source)<p class="mt-3 text-xs text-slate-400">Source: {{ $isExaminer ? $a->marks_source : (collect(explode(' › ', $a->marks_source))->slice(1)->implode(' › ') ?: 'Entered manually') }}</p>@endif
      @if ($a->examiner_commentary)<div class="card-soft mt-5 p-4"><p class="kicker">Examiner’s commentary</p><p class="mt-1.5 whitespace-pre-wrap text-sm">{{ $a->examiner_commentary }}</p></div>@endif
      @if ($samples->isNotEmpty())<div class="mt-5 grid gap-2 sm:grid-cols-2">@foreach ($samples as $s)<x-file-chip :name="$s->filename" :meta="number_format($s->size / 1024).' KB'" :href="route('files.show', $s)" />@endforeach</div>@endif
    </section>
  @endif
  @if ($a->records->isNotEmpty())
    <section class="card p-6 sm:p-8"><p class="kicker">Moderation</p><h2 class="mb-4 mt-1 text-xl font-semibold">Decisions & feedback</h2>
      <ul class="space-y-3">@foreach ($a->records as $r)
        <li class="card-soft flex gap-3 p-4"><x-icon :name="$r->approved() ? 'check-circle' : 'reply'" class="mt-0.5 h-5 w-5 shrink-0 {{ $r->approved() ? 'text-emerald-500' : 'text-amber-500' }}" />
          <div class="min-w-0 text-sm"><p class="font-semibold">{{ $r->stage->label() }} <span class="font-medium {{ $r->approved() ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">· {{ $r->approved() ? 'Approved' : 'Revision requested' }}</span></p>
            <p class="text-xs text-slate-500 dark:text-zinc-400">{{ $r->reviewer->name }} · {{ $r->created_at->utc()->format('j M Y, H:i') }} UTC{{ $r->scripts_sampled !== null ? ' · '.$r->scripts_sampled.' scripts sampled' : '' }}</p>
            @if ($r->comments)<p class="mt-1.5 whitespace-pre-wrap">{{ $r->comments }}</p>@endif</div></li>
      @endforeach</ul></section>
  @endif
</div>
