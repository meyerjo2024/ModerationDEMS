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
        $email = mb_strtolower((string) env('DEMS_SEED_EMAIL', 'hod@example.edu'));
        $password = (string) env('DEMS_SEED_PASSWORD', '');

        if (strlen($password) < 10) {
            if (User::where('role', Role::Hod->value)->exists()) {
                $this->command?->info('DEMS_SEED_PASSWORD not set — an HOD already exists, skipping seed.');

                return;
            }
            throw new RuntimeException('Set DEMS_SEED_PASSWORD (min 10 characters) so the first HOD account can be created.');
        }

        $hod = $this->user(env('DEMS_SEED_NAME', 'Head of Department'), $email, Role::Hod, $password, 'Administration');
        $this->command?->info("✔ HOD account ready: {$hod->email}");

        if (! config('dems.demo')) {
            return;
        }
        $this->user('Dr Amara Okafor', 'examiner@example.edu', Role::Examiner, $password, 'Computer Science');
        $this->user('Prof Liam van der Merwe', 'moderator@example.edu', Role::InternalModerator, $password, 'Computer Science');
        $this->user('Dr Priya Naidoo', 'external@example.edu', Role::ExternalModerator, $password, 'External');
        Subject::firstOrCreate(['code' => 'CSC101'], ['name' => 'Introduction to Programming', 'department' => 'Computer Science', 'hod_id' => $hod->id]);
        Subject::firstOrCreate(['code' => 'INF202'], ['name' => 'Information Systems II', 'department' => 'Computer Science', 'hod_id' => $hod->id]);
        $this->command?->info('✔ Demo users and subjects ready (password = DEMS_SEED_PASSWORD).');
    }

    private function user(string $name, string $email, Role $role, string $password, string $dept): User
    {
        return User::firstOrCreate(['email' => $email], ['name' => $name, 'role' => $role, 'password' => $password, 'department' => $dept, 'active' => true]);
    }
}
