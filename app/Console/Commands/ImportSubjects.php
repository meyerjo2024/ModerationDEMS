<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\SubjectImporter;
use Illuminate\Console\Command;

class ImportSubjects extends Command
{
    protected $signature = 'dems:import-subjects {file? : .xlsx/.xls/.csv (default: database/data/Subjects.xlsx)}';

    protected $description = 'Import the subject list (Qualification, Subject Code, Subject Name)';

    public function handle(SubjectImporter $importer): int
    {
        $file = $this->argument('file') ?? database_path('data/Subjects.xlsx');
        $hod = User::withRole(Role::Hod)->where('active', true)->orderBy('id')->first();
        if (! is_file($file) || ! $hod) {
            $this->error(! $hod ? 'Create an HOD account first.' : "File not found: {$file}");

            return self::FAILURE;
        }
        $r = $importer->import($file, $hod);
        $this->info("Subjects: {$r['created']} created, {$r['updated']} updated, {$r['skipped']} unchanged.");

        return self::SUCCESS;
    }
}
