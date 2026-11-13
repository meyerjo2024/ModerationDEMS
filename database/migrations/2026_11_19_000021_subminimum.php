<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A subminimum: the least percentage a student must reach in this test (set in the test calendar, confirmed in Section 1).
        foreach (['assessment_plans', 'assessments'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('subminimum')->default(false);
                $t->decimal('subminimum_percent', 5, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['assessment_plans', 'assessments'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['subminimum', 'subminimum_percent']));
        }
    }
};
