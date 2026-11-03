<?php

namespace App\Console\Commands;

use App\Models\Assessment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Removes the sample accounts, subjects and records created by earlier demo deployments. Never touches real accounts. */
class PurgeDemoData extends Command
{
    protected $signature = 'dems:purge-demo {--force : do not ask for confirmation}';

    protected $description = 'Delete the demo accounts (…@example.edu), their assessments and the demo subjects';

    private const EMAILS = ['examiner@example.edu', 'moderator@example.edu', 'external@example.edu', 'dual@example.edu'];

    /** The sample head of department: removed too, but only once a real HOD exists to take over its subjects. */
    private const SAMPLE_HOD = 'hod@example.edu';

    private const SUBJECTS = ['CSC101', 'INF202', 'MAT101'];

    public function handle(): int
    {
        // the first HOD (DEMS_SEED_EMAIL) is a real account even when it is called hod@example.edu
        $keep = mb_strtolower((string) env('DEMS_SEED_EMAIL', ''));
        $realHod = User::withRole(\App\Enums\Role::Hod)->where('active', true)->where('email', '!=', self::SAMPLE_HOD)->when($keep, fn ($q) => $q->orderByRaw('email = ? desc', [$keep]))->orderBy('id')->first();
        $emails = self::EMAILS;
        if ($realHod && $keep !== self::SAMPLE_HOD) {
            $emails[] = self::SAMPLE_HOD;
        }
        $users = User::whereIn('email', $emails)->when($keep, fn ($q) => $q->where('email', '!=', $keep))->get();
        $ids = $users->pluck('id');
        $assessments = Assessment::whereIn('examiner_id', $ids)->orWhereIn('internal_moderator_id', $ids)->orWhereIn('external_moderator_id', $ids)->pluck('id');
        $this->info("Demo accounts: {$users->count()} · their assessments: {$assessments->count()}");
        if (! $users->count() && ! $assessments->count() && ! Subject::whereIn('code', self::SUBJECTS)->exists()) {
            $this->info('Nothing to remove.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Permanently delete these?')) {
            return self::FAILURE;
        }
        DB::transaction(function () use ($ids, $assessments, $realHod) {
            if ($realHod) { // subjects imported under the sample HOD move to the real one
                Subject::whereIn('hod_id', $ids)->update(['hod_id' => $realHod->id]);
            }
            // children first; the audit log keeps no foreign key to users so history stays intact
            Assessment::whereIn('id', $assessments)->each(fn (Assessment $a) => $a->delete());
            DB::table('subject_user')->whereIn('user_id', $ids)->delete();
            DB::table('notifications')->whereIn('user_id', $ids)->delete();
            DB::table('sessions')->whereIn('user_id', $ids)->delete();
            DB::table('signatures')->whereIn('user_id', $ids)->delete();
            DB::table('moderation_records')->whereIn('reviewer_id', $ids)->delete();
            DB::table('audit_logs')->whereIn('user_id', $ids)->delete(); // sign-ins etc.; assessment trails went with the assessments
            DB::table('document_comments')->whereIn('user_id', $ids)->delete();
            Subject::whereIn('code', self::SUBJECTS)->whereDoesntHave('assessments')->each(fn (Subject $s) => $s->delete());
            User::whereIn('id', $ids)->delete();
        });
        $this->info('Demo data removed.');

        return self::SUCCESS;
    }
}
