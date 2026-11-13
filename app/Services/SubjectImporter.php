<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Reads the subject list (columns: Qualification, Subject Code, Subject Name; optional: Qualification Code). Idempotent. */
class SubjectImporter
{
    /** @return array{created: int, updated: int, skipped: int} */
    public function import(string $path, User $hod): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        $head = array_map(fn ($c) => mb_strtolower(trim((string) $c)), array_shift($rows) ?? []);
        $q = array_search('qualification', $head, true);
        $c = array_search('subject code', $head, true);
        $n = array_search('subject name', $head, true);
        $qc = array_search('qualification code', $head, true);
        if ($q === false || $c === false || $n === false) {
            throw new \InvalidArgumentException('The first row must contain the columns Qualification, Subject Code and Subject Name.');
        }

        // a code can be offered in several qualifications — keep one subject and list all of them
        $subjects = [];
        foreach ($rows as $r) {
            $code = mb_strtoupper(trim((string) ($r[$c] ?? '')));
            if ($code === '') {
                continue;
            }
            $name = trim((string) ($r[$n] ?? ''));
            $subjects[$code]['name'] ??= ($name !== '' && $name === mb_strtoupper($name)) ? mb_convert_case($name, MB_CASE_TITLE) : $name;
            $qual = trim((string) ($r[$q] ?? ''));
            if ($qual !== '' && ! in_array($qual, $subjects[$code]['quals'] ?? [], true)) {
                $subjects[$code]['quals'][] = $qual;
            }
            if ($qual !== '' && $qc !== false && trim((string) ($r[$qc] ?? '')) !== '') {
                $subjects[$code]['codes'][$qual] = trim((string) $r[$qc]);
            }
        }

        $out = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        foreach ($subjects as $code => $s) {
            $qual = implode(', ', $s['quals'] ?? []) ?: null;
            $codes = collect($s['quals'] ?? [])->map(fn ($x) => $s['codes'][$x] ?? '');
            $qualCode = $codes->filter()->isNotEmpty() ? $codes->implode(', ') : null;
            $existing = Subject::where('code', $code)->first();
            if (! $existing) {
                Subject::create(['code' => $code, 'name' => $s['name'] ?: $code, 'qualification' => $qual, 'qualification_code' => $qualCode, 'hod_id' => $hod->id]);
                $out['created']++;
            } elseif ($existing->qualification !== $qual || ($qualCode !== null && $existing->qualification_code !== $qualCode)) {
                $existing->update(['qualification' => $qual] + ($qualCode !== null ? ['qualification_code' => $qualCode] : []));
                $out['updated']++;
            } else {
                $out['skipped']++;
            }
        }

        return $out;
    }
}
