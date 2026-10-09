<?php

namespace Tests\Unit;

use App\Exceptions\WorkflowException;
use App\Services\WorkbookReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\ClassListMarksheet;
use Tests\TestCase;

class WorkbookReaderTest extends TestCase
{
    private function marks(): array
    {
        return array_merge([72, 70, 45, 56, 47, 31, 49, 39], range(40, 61)); // 30 students
    }

    public function test_recognises_the_class_list_layout_in_a_legacy_xls(): void
    {
        $t2 = $this->marks();
        $t2[3] = null; // one student without a T2 mark
        $path = ClassListMarksheet::build($this->marks(), $t2);
        $sheets = (new WorkbookReader)->inspect(file_get_contents($path), 'PHE261S_OT01.xls');

        $this->assertCount(1, $sheets);
        $s = $sheets[0];
        $this->assertSame('class-list', $s['format']);
        $this->assertSame('PHE261S', $s['meta']['code']);
        $this->assertSame('PRE-HOSPITAL EMERGENCY CARE 2', $s['meta']['name']);
        $this->assertSame('2026', $s['meta']['year']);
        $this->assertSame(30, $s['meta']['students']);
        $this->assertSame(4, $s['meta']['tests']);

        // only T1..T4 are offered (not S_NAME, S_NO, the unlabelled numeric column, …)
        $this->assertSame(['T1', 'T2', 'T3', 'T4'], array_column($s['columns'], 'header'));
        $this->assertSame([30, 29, 0, 0], array_column($s['columns'], 'nonBlank'));
        $this->assertSame([20.0, 25.0, 25.0, 30.0], array_column($s['columns'], 'weight'));
    }

    public function test_reads_one_test_column_with_class_list_details(): void
    {
        $t2 = $this->marks();
        $t2[3] = null;
        $path = ClassListMarksheet::build($this->marks(), $t2);
        $col = (new WorkbookReader)->column(file_get_contents($path), 'x.xls', 'MAS - Marksheet', 10); // J = T2

        $this->assertSame('T2', $col['header']);
        $this->assertCount(30, $col['values']); // one entry per listed student
        $this->assertNull($col['values'][3]);
        $this->assertSame(30, $col['enrolled']);
        $this->assertSame(25.0, $col['weight']);
        $this->assertSame('PHE261S', $col['meta']['code']);
    }

    public function test_plain_workbooks_still_work(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['Student', 'T1'], ['a', 50], ['b', 60]], null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'g').'.xlsx';
        (new Xlsx($book))->save($path);
        $s = (new WorkbookReader)->inspect(file_get_contents($path), 'g.xlsx')[0];
        $this->assertSame('generic', $s['format']);
        $this->assertSame(['Student', 'T1'], array_column($s['columns'], 'header'));
    }

    public function test_rejects_files_that_are_not_workbooks(): void
    {
        $this->expectException(WorkflowException::class);
        (new WorkbookReader)->inspect('%PDF-1.4 not a workbook', 'fake.xls');
    }
}
