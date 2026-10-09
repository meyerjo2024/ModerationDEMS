<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

/** Reads the examiner's marks workbook (.xlsx / .csv, header in row 1). */
class WorkbookReader
{
    /** @return list<array{name: string, columns: list<array{index: int, header: string, nonBlank: int}>}> */
    public function inspect(string $bytes, string $filename): array
    {
        return $this->withBook($bytes, $filename, function (Spreadsheet $book) {
            $sheets = [];
            foreach ($book->getAllSheets() as $ws) {
                $rows = $this->rows($ws);
                $header = $rows[0] ?? [];
                $columns = [];
                for ($c = 0; $c < min(count($header), 200); $c++) {
                    $h = $this->cell($header[$c] ?? null);
                    $nonBlank = 0;
                    foreach (array_slice($rows, 1) as $row) {
                        $v = $this->cell($row[$c] ?? null);
                        if ($v !== null && trim((string) $v) !== '') {
                            $nonBlank++;
                        }
                    }
                    if (($h === null || trim((string) $h) === '') && $nonBlank === 0) {
                        continue;
                    }
                    $columns[] = [
                        'index' => $c + 1,
                        'header' => ($h === null || trim((string) $h) === '') ? 'Column '.($c + 1) : trim((string) $h),
                        'nonBlank' => $nonBlank,
                    ];
                }
                $sheets[] = ['name' => $ws->getTitle(), 'columns' => $columns];
            }

            return $sheets;
        });
    }

    /** @return array{header: string, values: list<mixed>} */
    public function column(string $bytes, string $filename, string $sheet, int $columnIndex): array
    {
        return $this->withBook($bytes, $filename, function (Spreadsheet $book) use ($sheet, $columnIndex) {
            $ws = $book->getSheetByName($sheet);
            if (! $ws) {
                throw new WorkflowException("Sheet \"{$sheet}\" was not found in the workbook.");
            }
            $rows = $this->rows($ws);
            $h = $this->cell($rows[0][$columnIndex - 1] ?? null);
            $values = [];
            foreach (array_slice($rows, 1) as $row) {
                $values[] = $this->cell($row[$columnIndex - 1] ?? null);
            }

            return ['header' => ($h === null || trim((string) $h) === '') ? "Column {$columnIndex}" : trim((string) $h), 'values' => $values];
        });
    }

    private function rows($ws): array
    {
        if ($ws->getHighestDataRow() - 1 > config('dems.max_marks_rows')) {
            throw new WorkflowException('Sheets are limited to '.number_format(config('dems.max_marks_rows')).' rows.');
        }

        return $ws->toArray(null, true, false, false);
    }

    private function cell(mixed $v): mixed
    {
        return is_object($v) ? (string) $v : $v;
    }

    private function withBook(string $bytes, string $filename, callable $fn): mixed
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'csv' ? 'csv' : 'xlsx';
        $tmp = tempnam(sys_get_temp_dir(), 'dems').'.'.$ext;
        file_put_contents($tmp, $bytes);
        try {
            $reader = IOFactory::createReader($ext === 'csv' ? 'Csv' : 'Xlsx');
            $reader->setReadDataOnly(true);
            $book = $reader->load($tmp);
            try {
                return $fn($book);
            } finally {
                $book->disconnectWorksheets();
            }
        } catch (WorkflowException $e) {
            throw $e;
        } catch (Throwable) {
            throw new WorkflowException('That file could not be read. Please upload a valid .xlsx or .csv workbook.');
        } finally {
            @unlink($tmp);
            @unlink(substr($tmp, 0, -strlen($ext) - 1));
        }
    }
}
