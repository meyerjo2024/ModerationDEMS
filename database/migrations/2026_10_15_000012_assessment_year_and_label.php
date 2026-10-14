<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The same test (T1…) recurs every year, and supplementary / special sittings can repeat: identify an
        // assessment by subject + number + academic year + an optional note (e.g. "Aegrotat group").
        Schema::table('assessments', function (Blueprint $t) {
            $t->unsignedSmallInteger('academic_year')->default(0);
            $t->string('label', 80)->default('');
        });
        foreach (DB::table('assessments')->select('id', 'created_at', 'assessment_date')->get() as $a) {
            $y = (int) substr((string) ($a->assessment_date ?: $a->created_at), 0, 4);
            DB::table('assessments')->where('id', $a->id)->update(['academic_year' => $y ?: (int) date('Y')]);
        }
        Schema::table('assessments', function (Blueprint $t) {
            $t->dropUnique(['subject_id', 'number']);
            $t->unique(['subject_id', 'number', 'academic_year', 'label']);
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $t) {
            $t->dropUnique(['subject_id', 'number', 'academic_year', 'label']);
            $t->unique(['subject_id', 'number']);
            $t->dropColumn(['academic_year', 'label']);
        });
    }
};
