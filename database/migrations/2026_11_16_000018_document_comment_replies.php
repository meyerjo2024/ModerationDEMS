<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A conversation under each reviewer comment: the examiner and the moderator reply to one another.
        Schema::create('document_comment_replies', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('comment_id')->constrained('document_comments')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->text('body');
            $t->timestamps();
            $t->index('comment_id');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "document_comment_replies" ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_comment_replies');
    }
};
