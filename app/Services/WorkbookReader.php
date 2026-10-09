<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

/**
 * Reads the examiner's marks workbook (.xls, .xlsx, .csv).
 *
 * Two layouts are understood:
 *  - "class-list": the institutional class-list marksheet — a block of class details at the top
 *    (code, year, lecturer, number of students, "Test Weights"), a heading row containing S_NAME / S_NO and
 *    T1…Tn, then one row per student. Only the T-columns are offered; metadata is extracted.
 *  - "generic": headings in row 1, every column offered.
 *
 * Student names / numbers are never read into the application — only the chosen mark column is.
 */
class WorkbookReader
{
    /** @return list<array{name: string, format: string, meta: array<string, mixed>, columns: list<array{index: int, header: string, nonBlank: int, weight: float|null}>}> */
    public function inspect(string $bytes, string $filename): array
    {
        return $this->withBook($bytes, $filename, function (Spreadsheet $book) {
            $sheets = [];
            foreach ($book->getAllSheets() as $ws) {
                $rows = $this->rows($ws);
                $layout = $this->layout($rows);
                $header = $rows[$layout['header']] ?? [];
                $columns = [];
                for ($c = 0; $c < min(count($header), 200); $c++) {
                    $h = $this->cell($header[$c] ?? null);
                    $label = ($h === null) ? '' : trim((string) $h);
                    if ($layout['format'] === 'class-list' && ! preg_match('/^T\d+$/i', $label)) {
                        continue; // only test columns
                    }
                    $nonBlank = 0;
                    foreach ($layout['data'] as $r) {
                        $v = $this->cell($rows[$r][$c] ?? null);
                        if ($v !== null && trim((string) $v) !== '') {
                            $nonBlank++;
                        }
                    }
                    if ($label === '' && $nonBlank === 0) {
                        continue;
                    }
                    $columns[] = [
                        'index' => $c + 1,
                        'header' => $label === '' ? 'Column '.($c + 1) : $label,
                        'nonBlank' => $nonBlank,
                        'weight' => $layout['weights'][$c] ?? null,
                    ];
                }
                $sheets[] = ['name' => $ws->getTitle(), 'format' => $layout['format'], 'meta' => $layout['meta'], 'columns' => $columns];
            }

            return $sheets;
        });
    }

    /** @return array{header: string, values: list<mixed>, meta: array<string, mixed>, weight: float|null, enrolled: int|null, format: string} */
    public function column(string $bytes, string $filename, string $sheet, int $columnIndex): array
    {
        return $this->withBook($bytes, $filename, function (Spreadsheet $book) use ($sheet, $columnIndex) {
            $ws = $book->getSheetByName($sheet);
            if (! $ws) {
                throw new WorkflowException("Sheet \"{$sheet}\" was not found in the workbook.");
            }
            $rows = $this->rows($ws);
            $layout = $this->layout($rows);
            $c = $columnIndex - 1;
            $h = $this->cell($rows[$layout['header']][$c] ?? null);
            $values = [];
            foreach ($layout['data'] as $r) {
                $values[] = $this->cell($rows[$r][$c] ?? null);
            }

            return [
                'header' => ($h === null || trim((string) $h) === '') ? "Column {$columnIndex}" : trim((string) $h),
                'values' => $values,
                'meta' => $layout['meta'],
                'weight' => $layout['weights'][$c] ?? null,
                'enrolled' => $layout['format'] === 'class-list' ? count($layout['data']) : null,
                'format' => $layout['format'],
            ];
        });
    }

    /**
     * Finds the heading row and the student rows.
     *
     * @param  list<list<mixed>>  $rows
     * @return array{format: string, header: int, data: list<int>, meta: array<string, mixed>, weights: array<int, float>}
     */
    private function layout(array $rows): array
    {
        $header = null;
        foreach (array_slice($rows, 0, 60, true) as $i => $row) {
            $cells = array_map(fn ($v) => strtoupper(trim((string) $this->cell($v))), $row);
            $tests = array_filter($cells, fn ($v) => (bool) preg_match('/^T\d+$/', $v));
            $ids = array_filter($cells, fn ($v) => in_array($v, ['S_NAME', 'S_NO', 'STUDENT NAME', 'STUDENT NO', 'STUDENT NUMBER'], true));
            if ($tests && $ids) {
                $header = $i;
                break;
            }
        }

        if ($header === null) {
            return ['format' => 'generic', 'header' => 0, 'data' => count($rows) > 1 ? range(1, count($rows) - 1) : [], 'meta' => [], 'weights' => []];
        }

        $idCols = [];
        foreach ($rows[$header] as $c => $v) {
            if (in_array(strtoupper(trim((string) $this->cell($v))), ['S_NAME', 'S_NO', 'STUDENT NAME', 'STUDENT NO', 'STUDENT NUMBER'], true)) {
                $idCols[] = $c;
            }
        }
        $data = [];
        for ($r = $header + 1; $r < count($rows); $r++) {
            foreach ($idCols as $c) {
                $v = $this->cell($rows[$r][$c] ?? null);
                if ($v !== null && trim((string) $v) !== '') {
                    $data[] = $r;
                    break;
                }
            }
        }

        [$meta, $weights] = $this->parseMeta(array_slice($rows, 0, $header));

        return ['format' => 'class-list', 'header' => $header, 'data' => $data, 'meta' => $meta, 'weights' => $weights];
    }

    /**
     * "Label:  value  value  Other label:  value" pairs from the class-details block, plus the "Test Weights" row.
     *
     * @param  list<list<mixed>>  $rows
     * @return array{0: array<string, mixed>, 1: array<int, float>}
     */
    private function parseMeta(array $rows): array
    {
        $raw = [];
        $weights = [];
        foreach ($rows as $row) {
            $first = null;
            foreach ($row as $v) {
                $v = $this->cell($v);
                if ($v !== null && trim((string) $v) !== '') {
                    $first = trim((string) $v);
                    break;
                }
            }
            if ($first !== null && strcasecmp($first, 'Test Weights') === 0) {
                foreach ($row as $c => $v) {
                    $v = $this->cell($v);
                    if (is_numeric($v)) {
                        $weights[$c] = (float) $v;
                    }
                }

                continue;
            }
            $label = null;
            $buf = [];
            $flush = function () use (&$raw, &$label, &$buf) {
                if ($label !== null && $buf) {
                    $raw[$label] = trim(implode(' ', $buf));
                }
                $label = null;
                $buf = [];
            };
            foreach ($row as $v) {
                $v = $this->cell($v);
                if ($v === null || trim((string) $v) === '') {
                    continue;
                }
                $s = trim((string) $v);
                if (str_ends_with($s, ':')) {
                    $flush();
                    $label = strtolower(trim($s, ': '));
                } elseif ($label !== null) {
                    $buf[] = $s;
                }
            }
            $flush();
        }

        $classList = $raw['class list for'] ?? '';
        $code = $classList !== '' ? strtok($classList, ' ') : null;
        $name = preg_match('/\((.*)\)?/', $classList, $m) ? trim($m[1], ' )') : null;
        $int = fn ($k) => isset($raw[$k]) && is_numeric($raw[$k]) ? (int) $raw[$k] : null;

        return [array_filter([
            'code' => $code ?: null,
            'name' => $name ?: null,
            'qualification' => $raw['qualification'] ?? null,
            'faculty' => $raw['faculty'] ?? null,
            'department' => $raw['department'] ?? null,
            'offering' => $raw['offering type'] ?? null,
            'year' => $raw['calendar year'] ?? null,
            'lecturer' => $raw['lecturer'] ?? null,
            'tests' => $int('total number of tests'),
            'students' => $int('total number of students'),
        ], fn ($v) => $v !== null && $v !== ''), $weights];
    }

    private function rows($ws): array
    {
        if ($ws->getHighestDataRow() > config('dems.max_marks_rows') + 80) {
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
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $ext = in_array($ext, ['xls', 'xlsx', 'csv'], true) ? $ext : 'xlsx';
        $tmp = tempnam(sys_get_temp_dir(), 'dems');
        $path = $tmp.'.'.$ext;
        rename($tmp, $path);
        file_put_contents($path, $bytes);
        try {
            $type = $ext === 'csv' ? 'Csv' : IOFactory::identify($path);
            if (! in_array($type, ['Xls', 'Xlsx', 'Csv'], true)) {
                throw new WorkflowException('Please upload an .xls, .xlsx or .csv workbook.');
            }
            $reader = IOFactory::createReader($type);
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
            try {
                return $fn($book);
            } finally {
                $book->disconnectWorksheets();
            }
        } catch (WorkflowException $e) {
            throw $e;
        } catch (Throwable) {
            throw new WorkflowException('That file could not be read. Please upload a valid .xls, .xlsx or .csv workbook.');
        } finally {
            @unlink($path);
        }
    }
}
