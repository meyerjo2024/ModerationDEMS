<?php

namespace App\Services;

use App\Exceptions\WorkflowException;

/**
 * Automated calculation engine for Section 2 (post-assessment statistics).
 *
 *  - Blank cells are ignored (not candidates).
 *  - Non-blank cells that are not valid marks (text such as "ABS", negatives, marks above the total)
 *    are excluded from the statistics and counted in `invalid_entries`.
 *  - Marks are converted to percentages of `$totalMarks`; "62%" is treated as already a percentage.
 *  - Pass = percentage >= $passMark (default 50).
 */
class StatisticsCalculator
{
    public const PASS_MARK = 50.0;

    /**
     * @param  array<int, mixed>  $raw
     * @return array{total_marks: float, pass_mark: float, candidate_count: int, pass_count: int, pass_rate: float, highest_mark: float, lowest_mark: float, class_average: float, invalid_entries: int, scores: list<float>, distribution: list<int>}
     */
    public function calculate(array $raw, float $totalMarks = 100.0, ?float $passMark = null): array
    {
        $passMark ??= self::PASS_MARK;
        if (! ($totalMarks > 0) || ! is_finite($totalMarks)) {
            throw new WorkflowException('Total marks must be a positive number.');
        }

        $scores = [];
        $invalid = 0;
        foreach ($raw as $cell) {
            if ($this->isBlank($cell)) {
                continue;
            }
            $mark = $this->toMark($cell, $totalMarks);
            if ($mark === null || $mark < 0 || $mark > $totalMarks + 1e-9) {
                $invalid++;
            } else {
                $scores[] = round($mark, 2);
            }
        }
        if ($scores === []) {
            throw new WorkflowException('No valid marks were found in the selected data.');
        }

        $pct = array_map(fn ($s) => $s / $totalMarks * 100, $scores);
        $passCount = count(array_filter($scores, fn ($s) => $s * 100 >= $passMark * $totalMarks - 1e-9));
        $distribution = array_fill(0, 10, 0);
        foreach ($pct as $p) {
            $distribution[min(9, (int) floor($p / 10))]++;
        }
        $n = count($scores);

        return [
            'total_marks' => $totalMarks,
            'pass_mark' => $passMark,
            'candidate_count' => $n,
            'pass_count' => $passCount,
            'pass_rate' => round($passCount / $n * 100, 2),
            'highest_mark' => round(max($pct), 2),
            'lowest_mark' => round(min($pct), 2),
            'class_average' => round(array_sum($pct) / $n, 2),
            'invalid_entries' => $invalid,
            'scores' => $scores,
            'distribution' => $distribution,
        ];
    }

    /** Pasted marks: one per line / semicolon / tab / comma separated. @return list<string> */
    public function parseManual(string $text): array
    {
        $out = [];
        foreach (preg_split('/[\n\r\t;]+/', $text) ?: [] as $line) {
            if (preg_match('/^\s*-?\d+,\d+\s*$/', $line)) {
                $out[] = $line; // decimal comma
            } else {
                array_push($out, ...explode(',', $line));
            }
        }

        return $out;
    }

    private function isBlank(mixed $v): bool
    {
        return $v === null || (is_string($v) && trim($v) === '');
    }

    private function toMark(mixed $v, float $total): ?float
    {
        if (is_int($v) || is_float($v)) {
            return is_finite((float) $v) ? (float) $v : null;
        }
        if (! is_string($v)) {
            return null;
        }
        $s = trim($v);
        $isPct = str_ends_with($s, '%');
        if ($isPct) {
            $s = trim(substr($s, 0, -1));
        }
        if (preg_match('/^-?\d+,\d+$/', $s)) {
            $s = str_replace(',', '.', $s);
        }
        if (! preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return null;
        }
        $n = (float) $s;

        return $isPct ? $n / 100 * $total : $n;
    }
}
