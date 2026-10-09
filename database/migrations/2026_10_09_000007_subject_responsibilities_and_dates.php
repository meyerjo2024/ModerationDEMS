<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', fn (Blueprint $t) => $t->string('qualification', 200)->nullable());
        // The date the assessment is written: pre-moderation is due 14 days before, post-moderation 14 days after.
        Schema::table('assessments', fn (Blueprint $t) => $t->date('assessment_date')->nullable()->index());
        // Which subjects an examiner / internal moderator is responsible for.
        Schema::create('subject_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $t->string('role', 32);
            $t->unique(['user_id', 'subject_id', 'role']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "subject_user" ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_user');
        Schema::table('assessments', fn (Blueprint $t) => $t->dropColumn('assessment_date'));
        Schema::table('subjects', fn (Blueprint $t) => $t->dropColumn('qualification'));
    }
};
