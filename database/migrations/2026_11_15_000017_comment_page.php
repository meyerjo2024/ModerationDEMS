<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PDF comments are anchored to a page (and the quoted passage on it).
        Schema::table('document_comments', fn (Blueprint $t) => $t->unsignedInteger('page')->nullable());
    }

    public function down(): void
    {
        Schema::table('document_comments', fn (Blueprint $t) => $t->dropColumn('page'));
    }
};
