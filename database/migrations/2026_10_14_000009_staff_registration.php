<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staff register themselves; the Head of Department approves them (and the roles) before they can sign in.
        Schema::table('users', function (Blueprint $t) {
            $t->string('approval_status', 16)->default('approved')->index();
            $t->string('requested_roles', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['approval_status', 'requested_roles']));
    }
};
