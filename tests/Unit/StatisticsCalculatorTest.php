<?php

namespace Tests\Unit;

use App\Exceptions\WorkflowException;
use App\Services\StatisticsCalculator;
use PHPUnit\Framework\TestCase;

class StatisticsCalculatorTest extends TestCase
{
    private function calc(): StatisticsCalculator
    {
        return new StatisticsCalculator;
    }

    public function test_counts_non_blank_rows_pass_rate_and_metrics(): void
    {
        $r = $this->calc()->calculate([80, 50, 49.9, null, '', '  ', 30, 100], 100, 50);
        $this->assertSame(5, $r['candidate_count']);
        $this->assertSame(3, $r['pass_count']); // exactly 50 passes
        $this->assertSame(60.0, $r['pass_rate']);
        $this->assertSame(100.0, $r['highest_mark']);
        $this->assertSame(30.0, $r['lowest_mark']);
        $this->assertSame(61.98, $r['class_average']);
        $this->assertSame(0, $r['invalid_entries']);
    }

    public function test_converts_marks_to_percentages_of_the_total(): void
    {
        $r = $this->calc()->calculate([25, 30, 10, 45], 50, 50);
        $this->assertSame(75.0, $r['pass_rate']);
        $this->assertSame(90.0, $r['highest_mark']);
        $this->assertSame(20.0, $r['lowest_mark']);
        $this->assertSame(55.0, $r['class_average']);
    }

    public function test_flags_invalid_entries_instead_of_using_them(): void
    {
        $r = $this->calc()->calculate([60, 'ABS', 'DNS', -5, 120, '70']);
        $this->assertSame(2, $r['candidate_count']);
        $this->assertSame(4, $r['invalid_entries']);
    }

    public function test_understands_percent_strings_and_decimal_commas(): void
    {
        $r = $this->calc()->calculate(['62%', '45,5', 70]);
        $this->assertEquals([62.0, 45.5, 70.0], $r['scores']);
    }

    public function test_builds_a_ten_band_distribution(): void
    {
        $r = $this->calc()->calculate([0, 9, 10, 55, 99, 100]);
        $this->assertSame([2, 1, 0, 0, 0, 1, 0, 0, 0, 2], $r['distribution']);
    }

    public function test_rejects_empty_data_and_bad_totals(): void
    {
        $this->expectException(WorkflowException::class);
        $this->calc()->calculate([null, '', 'ABS']);
    }

    public function test_rejects_non_positive_total(): void
    {
        $this->expectException(WorkflowException::class);
        $this->calc()->calculate([50], 0);
    }

    public function test_parses_pasted_marks(): void
    {
        $this->assertSame(['55', '60', ' 70', '80', '90'], $this->calc()->parseManual("55\n60, 70;80\t90"));
        $this->assertSame(['45,5', '50'], $this->calc()->parseManual("45,5\n50"));
    }
}
