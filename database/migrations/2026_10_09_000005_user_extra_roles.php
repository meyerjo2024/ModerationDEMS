<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A person has one primary role plus optional extra ones (e.g. a Head of Department who also examines).
        Schema::table('users', function (Blueprint $t) {
            $t->string('extra_roles', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('extra_roles'));
    }
};
