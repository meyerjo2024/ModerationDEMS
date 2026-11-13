<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Creates staff accounts from a spreadsheet (columns: Name, Email, Roles, Department). Existing e-mails are never touched. */
class StaffImporter
{
    /** @return array{created: list<User>, skipped: list<string>, errors: list<string>} */
    public function import(string $path, string $password, array $assignable, callable $splitRoles): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        $head = array_map(fn ($c) => mb_strtolower(trim((string) $c)), array_shift($rows) ?? []);
        $col = fn (string ...$names) => collect($names)->map(fn ($n) => array_search($n, $head, true))->first(fn ($i) => $i !== false);
        $n = $col('name', 'full name');
        $e = $col('email', 'e-mail', 'email address');
        $r = $col('roles', 'role');
        $d = $col('department');
        $q = $col('qualifications', 'qualification', 'programme', 'programmes');
        $known = collect(\App\Models\Subject::allQualifications())->keyBy(fn ($x) => mb_strtolower($x));
        if ($n === null || $e === null || $r === null) {
            throw new \InvalidArgumentException('The first row must contain the columns Name, Email and Roles (Department and, for a Head of Programme, Qualifications are optional columns).');
        }
        $allowed = array_map(fn (Role $x) => $x->value, $assignable);
        $out = ['created' => [], 'skipped' => [], 'errors' => []];
        $seen = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $name = trim((string) ($row[$n] ?? ''));
            $email = mb_strtolower(trim((string) ($row[$e] ?? '')));
            if ($name === '' && $email === '') {
                continue;
            }
            if (mb_strlen($name) < 2 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out['errors'][] = "Row {$line}: needs a name and a valid e-mail.";
                continue;
            }
            $roles = $this->roles((string) ($row[$r] ?? ''), $allowed);
            if ($roles === null || $roles === []) {
                $out['errors'][] = "Row {$line} ({$email}): roles not recognised — use Examiner, Internal Moderator, External Moderator, HOD or Head of Programme".(in_array(Role::SystemAdmin->value, $allowed, true) ? ' (or System Administrator)' : '').', separated by commas.';
                continue;
            }
            if (isset($seen[$email]) || User::where('email', $email)->exists()) {
                $out['skipped'][] = $email;
                continue;
            }
            $programmes = null;
            if (in_array('HEAD_OF_PROGRAMME', $roles, true)) {
                $programmes = collect(preg_split('/[;|]+/', $q !== null ? (string) ($row[$q] ?? '') : ''))->map(fn ($x) => $known->get(mb_strtolower(trim($x))))->filter()->unique()->values()->all();
                if (! $programmes) {
                    $out['errors'][] = "Row {$line} ({$email}): a Head of Programme needs a Qualifications cell naming an existing qualification (separate several with ;).";
                    continue;
                }
            }
            $seen[$email] = true;
            [$primary, $extra] = $splitRoles($roles);
            $out['created'][] = User::create(['name' => $name, 'email' => $email, 'role' => $primary, 'extra_roles' => $extra,
                'programme_qualifications' => $programmes, 'department' => ($d !== null ? trim((string) ($row[$d] ?? '')) : '') ?: null, 'password' => $password, 'active' => true, 'approval_status' => 'approved']);
        }

        return $out;
    }

    /** @return list<string>|null null when a word is not a role this person may hand out */
    private function roles(string $cell, array $allowed): ?array
    {
        $map = ['examiner' => 'EXAMINER', 'internal moderator' => 'INTERNAL_MODERATOR', 'internal' => 'INTERNAL_MODERATOR', 'external moderator' => 'EXTERNAL_MODERATOR', 'external' => 'EXTERNAL_MODERATOR',
            'hod' => 'HOD', 'head of programme' => 'HEAD_OF_PROGRAMME', 'programme head' => 'HEAD_OF_PROGRAMME', 'hop' => 'HEAD_OF_PROGRAMME', 'head of department' => 'HOD', 'system administrator' => 'SYSTEM_ADMIN', 'system admin' => 'SYSTEM_ADMIN', 'admin' => 'SYSTEM_ADMIN'];
        $out = [];
        foreach (preg_split('/[,;\/|]+/', $cell) as $w) {
            $w = mb_strtolower(trim($w));
            if ($w === '') {
                continue;
            }
            $v = $map[$w] ?? null;
            if ($v === null || ! in_array($v, $allowed, true)) {
                return null;
            }
            $out[] = $v;
        }

        return array_values(array_unique($out));
    }
}
