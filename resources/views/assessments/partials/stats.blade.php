<x-stats :s="['candidates' => $a->candidate_count, 'passed' => $a->pass_count, 'pass_rate' => $a->pass_rate, 'average' => $a->class_average, 'highest' => $a->highest_mark, 'lowest' => $a->lowest_mark, 'invalid' => $a->invalid_entries]"
  :distribution="$a->scores && $a->total_marks ? app(\App\Services\StatisticsCalculator::class)->calculate($a->scores, $a->total_marks)['distribution'] : null" />
