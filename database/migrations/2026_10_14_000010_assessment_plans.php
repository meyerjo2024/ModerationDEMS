<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An examiner's calendar: which test (T1…) of which subject is written when. Drives the reminders.
        Schema::create('assessment_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $t->string('number', 8);
            $t->date('assessment_date');
            $t->timestamps();
            $t->unique(['user_id', 'subject_id', 'number']);
            $t->index('assessment_date');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "assessment_plans" ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_plans');
    }
};
