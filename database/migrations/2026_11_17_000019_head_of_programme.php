<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->json('programme_qualifications')->nullable();   // the qualification(s) a Head of Programme heads
            $t->string('programme_request', 255)->nullable();   // what they typed when registering
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['programme_qualifications', 'programme_request']));
    }
};
