<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The qualification (name + code) and test date travel with the record, so every section shows the same details.
        Schema::table('subjects', fn (Blueprint $t) => $t->string('qualification_code', 200)->nullable()); // codes in the same order as the names
        Schema::table('assessments', function (Blueprint $t) {
            $t->string('qualification', 200)->nullable();
            $t->string('qualification_code', 40)->nullable();
        });
        // existing records: take the qualification Section 1 already holds, else the subject's first one
        foreach (\Illuminate\Support\Facades\DB::table('assessments')->get(['id', 'subject_id', 's1_examiner']) as $a) {
            $s1 = json_decode((string) $a->s1_examiner, true) ?: [];
            $subject = \Illuminate\Support\Facades\DB::table('subjects')->where('id', $a->subject_id)->first(['qualification']);
            $name = ($s1['qualification'] ?? '') ?: trim(explode(', ', (string) ($subject->qualification ?? ''))[0]);
            if ($name !== '') {
                \Illuminate\Support\Facades\DB::table('assessments')->where('id', $a->id)->update(['qualification' => $name, 'qualification_code' => ($s1['qualification_code'] ?? '') ?: null]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('assessments', fn (Blueprint $t) => $t->dropColumn(['qualification', 'qualification_code']));
        Schema::table('subjects', fn (Blueprint $t) => $t->dropColumn('qualification_code'));
    }
};
