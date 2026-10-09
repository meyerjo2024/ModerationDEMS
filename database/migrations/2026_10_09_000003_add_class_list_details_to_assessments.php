<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $t) {
            $t->unsignedInteger('enrolled_count')->nullable()->after('candidate_count'); // students listed on the class list
            $t->double('test_weight')->nullable()->after('enrolled_count');             // % of the year mark (class-list "Test Weights")
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $t) {
            $t->dropColumn(['enrolled_count', 'test_weight']);
        });
    }
};
