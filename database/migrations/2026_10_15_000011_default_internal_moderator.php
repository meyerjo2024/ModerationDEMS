<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An examiner's usual internal moderator for a subject; pre-selected when they start an assessment.
        Schema::table('subject_user', fn (Blueprint $t) => $t->foreignId('default_internal_moderator_id')->nullable()->constrained('users')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('subject_user', function (Blueprint $t) {
            $t->dropConstrainedForeignId('default_internal_moderator_id');
        });
    }
};
