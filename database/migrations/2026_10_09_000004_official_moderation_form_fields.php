<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Content of the official "Appendix 2: Comprehensive Moderation Report" (see config/moderation_form.php). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $t) {
            $t->json('s1_examiner')->nullable();   // period, year, levels, qualification, date, weightings
            $t->json('s1_moderator')->nullable();  // ratings + questions 1–3 (internal moderator, Gate 1)
            $t->json('s2_examiner')->nullable();   // registered, type of assessment, questions 1–5
            $t->json('s2_moderator')->nullable();  // questions 6–8, adjustments (internal moderator, Gate 2)
            $t->json('s3_external')->nullable();   // Section 3 comments + adjustments
            $t->dropColumn(['question_types', 'examiner_commentary']);
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $t) {
            $t->json('question_types')->nullable();
            $t->text('examiner_commentary')->nullable();
            $t->dropColumn(['s1_examiner', 's1_moderator', 's2_examiner', 's2_moderator', 's3_external']);
        });
    }
};
