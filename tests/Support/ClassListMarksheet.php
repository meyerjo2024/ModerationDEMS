<?php

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds an anonymised workbook in the institutional class-list ("Marksheet") layout:
 * 13 rows of class details + "Test Weights", headings on row 14 (S_NAME, S_NO … T1 T2 T3 T4), one row per student.
 * Names, numbers and lecturer are fictitious.
 */
class ClassListMarksheet
{
    /**
     * @param  array<int, int|float|null>  $t1  one mark per student (null = blank)
     * @param  array<int, int|float|null>  $t2
     * @return string path to the written file
     */
    public static function build(array $t1, array $t2 = [], string $code = 'PHE261S', string $format = 'xls', array $weights = [20, 25, 25, 30]): string
    {
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet()->setTitle('MAS - Marksheet');
        $n = count($t1);
        $ws->setCellValue('A1', 'CAPE PENINSULA UNIVERSITY OF TECHNOLOGY');
        $ws->setCellValue('A2', 'Class List for:')->setCellValue('C2', $code)->setCellValue('E2', '(PRE-HOSPITAL EMERGENCY CARE 2)');
        $ws->setCellValue('A3', 'Qualification:')->setCellValue('C3', 'ALL');
        $ws->setCellValue('A4', 'Faculty:')->setCellValueExplicit('C4', '180', 's')->setCellValue('E4', 'HEALTH & WELLNESS SCIENCES');
        $ws->setCellValue('A5', 'Department:')->setCellValueExplicit('C5', '184', 's')->setCellValue('E5', 'EMERGENCY MEDICAL SCIENCES');
        $ws->setCellValue('A6', 'Offering Type:')->setCellValueExplicit('C6', '01', 's')->setCellValue('E6', 'FULL-TIME: BELLVILLE CAMPUS');
        $ws->setCellValue('A7', 'Block Code:')->setCellValue('C7', 0)->setCellValue('D7', 'YEAR (JAN-NOV)');
        $ws->setCellValue('A8', 'Calendar Year:')->setCellValue('C8', 2026);
        $ws->setCellValue('A9', 'Lecturer:')->setCellValue('C9', '10000001 MS A LECTURER');
        $ws->setCellValue('A10', 'Mark Type:')->setCellValue('C10', 'TM')->setCellValue('D10', 'Classgroup:')->setCellValue('F10', 'A');
        $ws->setCellValue('A11', 'Class Group Type:')->setCellValue('C11', 'C')->setCellValue('H11', 'Total number of tests:')->setCellValue('N11', 4);
        $ws->setCellValue('A12', 'Aphabetic/Numeric:')->setCellValue('C12', 'A')->setCellValue('H12', 'Total Number of Students:')->setCellValue('N12', $n);
        $ws->setCellValue('A13', 'Test Weights');
        foreach (['I', 'J', 'K', 'L'] as $i => $col) {
            $ws->setCellValue($col.'13', $weights[$i]);
        }
        $ws->setCellValue('M13', array_sum($weights));
        foreach (['S_NAME', 'S_NO', 'PROG', 'FM', 'CG', 'QUAL', 'C_DATE', 'SN', 'T1', 'T2', 'T3', 'T4'] as $i => $h) {
            $ws->setCellValue([$i + 1, 14], $h);
        }
        for ($i = 0; $i < $n; $i++) {
            $r = 15 + $i;
            $ws->setCellValue("A{$r}", sprintf('STUDENT%02d,AB', $i + 1));
            $ws->setCellValueExplicit("B{$r}", (string) (900000001 + $i), 's');
            $ws->setCellValue("C{$r}", 25.5)->setCellValue("E{$r}", 'A')->setCellValue("F{$r}", 'D2EMCA')->setCellValue("H{$r}", 1);
            if (($t1[$i] ?? null) !== null) {
                $ws->setCellValue("I{$r}", $t1[$i]);
            }
            if (($t2[$i] ?? null) !== null) {
                $ws->setCellValue("J{$r}", $t2[$i]);
            }
            $ws->setCellValue("T{$r}", 45); // an unlabelled numeric column that must be ignored
        }
        $path = tempnam(sys_get_temp_dir(), 'cl').'.'.$format;
        ($format === 'xls' ? new Xls($book) : new Xlsx($book))->save($path);

        return $path;
    }
}
