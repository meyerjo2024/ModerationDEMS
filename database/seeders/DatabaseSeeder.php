<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Idempotent: safe to run on every deploy. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = mb_strtolower(trim((string) env('DEMS_SEED_EMAIL', '')));
        $password = (string) env('DEMS_SEED_PASSWORD', '');

        if ($email === '' || strlen($password) < 10) {
            if (User::where('role', Role::Hod->value)->exists()) {
                $this->command?->info('DEMS_SEED_EMAIL / DEMS_SEED_PASSWORD not set — an HOD already exists, skipping account seed.');
                $this->systemAdmin();
                $this->importSubjects();

                return;
            }
            throw new RuntimeException('Set DEMS_SEED_EMAIL and DEMS_SEED_PASSWORD (min 10 characters) so the first HOD account can be created.');
        }

        $hod = $this->user(env('DEMS_SEED_NAME', 'Head of Department'), $email, Role::Hod, $password, 'Administration');
        if (filter_var(env('DEMS_SEED_RESET_PASSWORD', false), FILTER_VALIDATE_BOOLEAN)) {
            // forgotten password: set DEMS_SEED_RESET_PASSWORD=true for one deploy, then remove it
            $hod->update(['password' => $password, 'active' => true]);
            $this->command?->info("✔ Password reset for {$hod->email}");
        }
        $this->command?->info("✔ HOD account ready: {$hod->email}");

        $this->systemAdmin();
        $this->importSubjects();
    }

    /** Optional: a System Administrator (platform management only). DEMS_ADMIN_NAME / DEMS_ADMIN_EMAIL / DEMS_ADMIN_PASSWORD. */
    private function systemAdmin(): void
    {
        $email = mb_strtolower(trim((string) env('DEMS_ADMIN_EMAIL', '')));
        $password = (string) env('DEMS_ADMIN_PASSWORD', '');
        if ($email === '' || strlen($password) < 10) {
            return;
        }
        $admin = $this->user(env('DEMS_ADMIN_NAME', 'System Administrator'), $email, Role::SystemAdmin, $password, 'IT');
        if (filter_var(env('DEMS_SEED_RESET_PASSWORD', false), FILTER_VALIDATE_BOOLEAN)) {
            $admin->update(['password' => $password, 'active' => true]);
        }
        $this->command?->info("✔ System Administrator ready: {$admin->email}");
    }

    private function importSubjects(): void
    {
        $file = database_path('data/Subjects.xlsx');
        $hod = User::withRole(Role::Hod)->where('active', true)->orderBy('id')->first();
        if ($hod && is_file($file)) {
            $r = app(\App\Services\SubjectImporter::class)->import($file, $hod);
            $this->command?->info("✔ Subject list: {$r['created']} new, {$r['updated']} updated.");
        }
    }

    private function user(string $name, string $email, Role $role, string $password, string $dept): User
    {
        return User::firstOrCreate(['email' => $email], ['name' => $name, 'role' => $role, 'password' => $password, 'department' => $dept, 'active' => true]);
    }
}
