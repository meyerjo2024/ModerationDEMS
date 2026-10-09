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
                $this->importSubjects();

                return;
            }
            throw new RuntimeException('Set DEMS_SEED_PASSWORD (min 10 characters) so the first HOD account can be created.');
        }

        $hod = $this->user(env('DEMS_SEED_NAME', 'Head of Department'), $email, Role::Hod, $password, 'Administration');
        $this->command?->info("✔ HOD account ready: {$hod->email}");

        $this->importSubjects();

        if (! config('dems.demo')) {
            return;
        }
        $this->user('Dr Amara Okafor', 'examiner@example.edu', Role::Examiner, $password, 'Computer Science');
        $this->user('Prof Liam van der Merwe', 'moderator@example.edu', Role::InternalModerator, $password, 'Computer Science');
        $this->user('Dr Priya Naidoo', 'external@example.edu', Role::ExternalModerator, $password, 'External');
        // One person, several roles: Head of Department of MAT101 who also examines and moderates other subjects.
        $dual = $this->user('Prof Thandi Mokoena', 'dual@example.edu', Role::Hod, $password, 'Mathematics');
        $dual->update(['extra_roles' => 'EXAMINER,INTERNAL_MODERATOR']);
        Subject::firstOrCreate(['code' => 'MAT101'], ['name' => 'Mathematics I', 'department' => 'Mathematics', 'hod_id' => $dual->id]);
        Subject::firstOrCreate(['code' => 'CSC101'], ['name' => 'Introduction to Programming', 'department' => 'Computer Science', 'hod_id' => $hod->id]);
        Subject::firstOrCreate(['code' => 'INF202'], ['name' => 'Information Systems II', 'department' => 'Computer Science', 'hod_id' => $hod->id]);
        // demo examiner / moderator already take responsibility for a few subjects
        foreach (['examiner@example.edu' => 'EXAMINER', 'moderator@example.edu' => 'INTERNAL_MODERATOR'] as $mail => $role) {
            $uid = User::where('email', $mail)->value('id');
            foreach (['CSC101', 'INF202', 'PHE261S', 'PHE262S', 'PHE263S'] as $code) {
                if ($sid = Subject::where('code', $code)->value('id')) {
                    \Illuminate\Support\Facades\DB::table('subject_user')->insertOrIgnore(['user_id' => $uid, 'subject_id' => $sid, 'role' => $role]);
                }
            }
        }
        $this->command?->info('✔ Demo users and subjects ready (password = DEMS_SEED_PASSWORD).');
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
