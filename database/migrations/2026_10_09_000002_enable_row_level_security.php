<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supabase exposes every table in the public schema through its REST API using the public "anon" key.
 * Row Level Security with NO policies blocks that path entirely; the app connects as the table owner
 * (postgres), which bypasses RLS, so the application itself is unaffected. No-op on other databases.
 */
return new class extends Migration
{
    private array $tables = [
        'users', 'sessions', 'subjects', 'assessments', 'moderation_records', 'signatures',
        'audit_logs', 'attachments', 'file_blobs', 'notifications', 'migrations',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        foreach ($this->tables as $table) {
            DB::statement('ALTER TABLE "'.$table.'" ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        foreach ($this->tables as $table) {
            DB::statement('ALTER TABLE "'.$table.'" DISABLE ROW LEVEL SECURITY');
        }
    }
};
