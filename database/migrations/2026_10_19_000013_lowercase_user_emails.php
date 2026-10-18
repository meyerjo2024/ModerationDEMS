<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Sign-in lower-cases what is typed, so accounts stored with capitals (e.g. created by an admin) could not sign in.
        foreach (DB::table('users')->get(['id', 'email']) as $u) {
            $low = mb_strtolower(trim($u->email));
            if ($low !== $u->email && ! DB::table('users')->where('email', $low)->where('id', '!=', $u->id)->exists()) {
                DB::table('users')->where('id', $u->id)->update(['email' => $low]);
            }
        }
    }

    public function down(): void {}
};
