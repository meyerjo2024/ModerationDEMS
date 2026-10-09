<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reviewers' comments on a Word document, anchored to the quoted passage; returned to the examiner with the record.
        Schema::create('document_comments', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('assessment_id')->constrained()->cascadeOnDelete();
            $t->foreignUlid('attachment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->text('quote')->nullable();
            $t->unsignedInteger('start_offset')->nullable();
            $t->text('body');
            $t->boolean('addressed')->default(false);
            $t->text('reply')->nullable();
            $t->timestamps();
            $t->index(['assessment_id', 'attachment_id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "document_comments" ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_comments');
    }
};
