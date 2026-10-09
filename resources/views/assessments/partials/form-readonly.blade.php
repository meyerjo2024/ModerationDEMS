{{-- Read-only rendering of the official form. $a, $sections = ['s1','s2','s3'] (any subset) --}}
@php
$f = config('moderation_form');
$s1e = $a->s1_examiner ?? []; $s1m = $a->s1_moderator ?? [];
$s2e = $a->s2_examiner ?? []; $s2m = $a->s2_moderator ?? [];
$s3 = $a->s3_external ?? [];
$yn = fn ($v) => $v === 'YES' ? 'Yes' : ($v === 'NO' ? 'No' : '—');
$rated = ['0' => 'Poor', '1' => 'Adequate', '2' => 'Good'];
$kv = fn ($k, $v) => '<div><dt class="kicker">'.e($k).'</dt><dd class="mt-0.5 font-medium">'.e($v === null || $v === '' ? '—' : $v).'</dd></div>';
@endphp

@if (in_array('s1', $sections) && $s1e)
<section class="card p-6 sm:p-8">
  <p class="kicker">Section 1 · Pre-assessment</p><h2 class="mt-1 text-xl font-semibold">Assessment details & types of questions</h2>
  <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
    {!! $kv('Assessment', ($f['periods'][$s1e['period'] ?? ''] ?? '').' '.($s1e['year'] ?? '')) !!}
    {!! $kv('Assessment no.', $a->number) !!}
    {!! $kv('Assessment date', ! empty($s1e['assessment_date']) ? \Illuminate\Support\Carbon::parse($s1e['assessment_date'])->format('d/m/Y') : null) !!}
    {!! $kv('HEQF level of subject', $s1e['heqf_level'] ?? null) !!}
    {!! $kv('Level of subject', $s1e['subject_level'] ?? null) !!}
    {!! $kv('Qualification', trim(($s1e['qualification'] ?? '').' '.(! empty($s1e['qualification_code']) ? '('.$s1e['qualification_code'].')' : ''))) !!}
  </dl>
  <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200/60 dark:border-white/10">
    <table class="w-full text-left text-sm">
      <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wider text-slate-500 dark:bg-white/5 dark:text-zinc-400"><tr><th class="px-4 py-2.5 font-semibold">Types of questions</th><th class="px-3 py-2.5 text-right font-semibold">% weighting</th></tr></thead>
      <tbody class="divide-y divide-slate-200/60 dark:divide-white/10">
        @foreach ($f['question_types'] as $k => $t)<tr><td class="px-4 py-2.5">{{ $t['label'] }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ ($s1e['weights'][$k] ?? 0) + 0 }}%</td></tr>@endforeach
      </tbody>
      <tfoot><tr class="bg-slate-50/60 text-xs font-semibold dark:bg-white/5"><td class="px-4 py-2">Total</td><td class="px-3 py-2 text-right tabular-nums">{{ round(array_sum($s1e['weights'] ?? []), 2) }}%</td></tr></tfoot>
    </table>
  </div>
  @if ($s1m)
    <p class="kicker mt-6">Internal moderator’s ratings</p>
    <ul class="mt-2 grid gap-2 sm:grid-cols-2">
      @foreach ($f['ratings'] as $k => $label)
        <li class="card-soft flex items-center justify-between gap-3 px-4 py-2.5 text-sm"><span>{{ $label }}</span><span class="rounded-full bg-accent/10 px-2.5 py-0.5 text-xs font-semibold text-accent dark:text-accent-dark">{{ $s1m['ratings'][$k] ?? '—' }} · {{ $rated[(string) ($s1m['ratings'][$k] ?? '')] ?? '' }}</span></li>
      @endforeach
    </ul>
    <ul class="mt-4 space-y-2">
      @foreach ($f['s1_questions'] as $k => $text)
        <li class="card-soft p-4 text-sm"><p class="font-medium">{{ substr($k, 1) }}. {{ $text }} <span class="ml-1 rounded-full bg-slate-900/5 px-2 py-0.5 text-xs font-semibold dark:bg-white/10">{{ $yn($s1m['questions'][$k]['answer'] ?? null) }}</span></p>
          @if (! empty($s1m['questions'][$k]['comment']))<p class="mt-1 whitespace-pre-wrap text-slate-600 dark:text-zinc-300">{{ $s1m['questions'][$k]['comment'] }}</p>@endif</li>
      @endforeach
    </ul>
  @endif
</section>
@endif

@if (in_array('s2', $sections) && $s2e)
<section class="card p-6 sm:p-8">
  <p class="kicker">Section 2 · Post-assessment</p><h2 class="mb-4 mt-1 text-xl font-semibold">Results & comments</h2>
  @include('assessments.partials.stats', ['a' => $a])
  <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
    {!! $kv('Registered candidates', $s2e['registered'] ?? null) !!}
    {!! $kv('Absent', isset($s2e['registered'], $a->candidate_count) ? max(0, $s2e['registered'] - $a->candidate_count) : null) !!}
    {!! $kv('Type of assessment', $s2e['type_of_assessment'] ?? null) !!}
  </dl>
  <ul class="mt-5 space-y-2">
    @foreach ($f['s2_examiner_questions'] as $k => $text)
      <li class="card-soft p-4 text-sm"><p class="font-medium">{{ substr($k, 1) }}. {{ $text }}</p><p class="mt-1 whitespace-pre-wrap text-slate-600 dark:text-zinc-300">{{ $s2e['answers'][$k] ?? '—' }}</p></li>
    @endforeach
    @if ($s2m)
      @foreach ($f['s2_moderator_questions'] as $k => $text)
        <li class="card-soft p-4 text-sm"><p class="font-medium">{{ substr($k, 1) }}. {{ $text }}</p><p class="mt-1 whitespace-pre-wrap text-slate-600 dark:text-zinc-300">{{ $s2m['answers'][$k] ?? '—' }}</p></li>
      @endforeach
      @foreach ($f['comment_items'] as $k => $text)
        <li class="card-soft p-4 text-sm"><p class="font-medium">{{ $text }}</p><p class="mt-1 whitespace-pre-wrap text-slate-600 dark:text-zinc-300">{{ $s2m['items'][$k] ?? '—' }}</p></li>
      @endforeach
      <li class="card-soft p-4 text-sm"><p class="font-medium">{{ $f['adjustments'] }} <span class="ml-1 rounded-full bg-slate-900/5 px-2 py-0.5 text-xs font-semibold dark:bg-white/10">{{ $yn($s2m['adjustments']['recommended'] ?? null) }}</span></p>
        @if (! empty($s2m['adjustments']['specify']))<p class="mt-1 whitespace-pre-wrap text-slate-600 dark:text-zinc-300">{{ $s2m['adjustments']['specify'] }}</p>@endif</li>
    @endif
  </ul>
</section>
@endif

@if (in_array('s3', $sections) && $s3)
<section class="card p-6 sm:p-8">
  <p class="kicker">Section 3 · External moderator</p><h2 class="mb-4 mt-1 text-xl font-semibold">External moderator’s comments</h2>
  <ul class="space-y-2">
    @foreach ($f['comment_items'] + $f['s3_extra_items'] as $k => $text)
      <li class="card-soft p-4 text-sm"><p class="font-medium">{{ $text }}</p><p class="mt-1 whitespace-pre-wrap text-slate-600 dark:text-zinc-300">{{ $s3['items'][$k] ?? '—' }}</p></li>
    @endforeach
    <li class="card-soft p-4 text-sm"><p class="font-medium">{{ $f['adjustments'] }} <span class="ml-1 rounded-full bg-slate-900/5 px-2 py-0.5 text-xs font-semibold dark:bg-white/10">{{ $yn($s3['adjustments']['recommended'] ?? null) }}</span></p>
      @if (! empty($s3['adjustments']['specify']))<p class="mt-1 whitespace-pre-wrap text-slate-600 dark:text-zinc-300">{{ $s3['adjustments']['specify'] }}</p>@endif</li>
  </ul>
</section>
@endif
