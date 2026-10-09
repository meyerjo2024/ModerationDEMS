<x-stats :s="['candidates' => $a->candidate_count, 'passed' => $a->pass_count, 'pass_rate' => $a->pass_rate, 'average' => $a->class_average, 'highest' => $a->highest_mark, 'lowest' => $a->lowest_mark, 'invalid' => $a->invalid_entries]"
  :distribution="$a->scores && $a->total_marks ? app(\App\Services\StatisticsCalculator::class)->calculate($a->scores, $a->total_marks)['distribution'] : null" />
@if ($a->enrolled_count)
  <p class="mt-3 text-xs text-slate-500 dark:text-zinc-400">{{ $a->candidate_count }} of {{ $a->enrolled_count }} listed students have a mark in this test @if ($a->test_weight) · test weight {{ $a->test_weight + 0 }}% of the year mark @endif</p>
@endif
