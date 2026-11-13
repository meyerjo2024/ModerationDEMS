<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An assessment can have several documents: each has a name (its "slot"); uploading the same name again replaces it,
        // and removing one keeps the file for the record (removed_at) so earlier hashes stay valid.
        Schema::table('attachments', function (Blueprint $t) {
            $t->string('label', 120)->nullable();
            $t->timestamp('removed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attachments', fn (Blueprint $t) => $t->dropColumn(['label', 'removed_at']));
    }
};
