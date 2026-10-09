<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->string('role', 32)->index();
            $t->string('department')->nullable();
            $t->boolean('active')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });

        Schema::create('subjects', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('department')->nullable();
            $t->foreignId('hod_id')->constrained('users');
            $t->timestamps();
        });

        Schema::create('assessments', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignId('subject_id')->constrained();
            $t->string('number', 40);
            $t->string('status', 40)->default('DRAFT')->index();
            $t->foreignId('examiner_id')->constrained('users');
            $t->foreignId('internal_moderator_id')->constrained('users');
            $t->foreignId('external_moderator_id')->nullable()->constrained('users');

            // Section 1 — [{ type, weighting, heqf_level, aligned, comment }]
            $t->json('question_types')->nullable();
            $t->unsignedInteger('revision')->default(0);

            // Section 2 — computed server-side; raw scores are anonymous numbers only.
            $t->double('total_marks')->nullable();
            $t->json('scores')->nullable();
            $t->unsignedInteger('candidate_count')->nullable();
            $t->unsignedInteger('pass_count')->nullable();
            $t->double('pass_rate')->nullable();
            $t->double('highest_mark')->nullable();
            $t->double('lowest_mark')->nullable();
            $t->double('class_average')->nullable();
            $t->unsignedInteger('invalid_entries')->nullable();
            $t->string('marks_source')->nullable();
            $t->text('examiner_commentary')->nullable();

            $t->timestamp('completed_at')->nullable();
            $t->timestamps();

            $t->unique(['subject_id', 'number']);
        });

        Schema::create('moderation_records', function (Blueprint $t) {
            $t->id();
            $t->foreignUlid('assessment_id')->constrained()->cascadeOnDelete();
            $t->string('stage', 32);
            $t->foreignId('reviewer_id')->constrained('users');
            $t->string('decision', 32);
            $t->boolean('consensus_reached')->default(false);
            $t->text('comments')->nullable();
            $t->json('checklist')->nullable();
            $t->unsignedInteger('scripts_sampled')->nullable();
            $t->timestamps();
        });

        Schema::create('signatures', function (Blueprint $t) {
            $t->id();
            $t->foreignUlid('assessment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->string('section', 40);
            $t->text('image_data');           // PNG data URL
            $t->string('content_hash', 64);   // SHA-256 of what the signer attested to
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 300)->nullable();
            $t->timestamp('signed_at');
            $t->index(['assessment_id', 'section']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignUlid('assessment_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('action', 60);
            $t->json('details')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('prev_hash', 64)->nullable();   // hash chain per assessment
            $t->string('hash', 64);
            $t->timestamp('created_at');
            $t->index(['assessment_id', 'id']);
        });

        Schema::create('attachments', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('assessment_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 24);
            $t->string('filename');
            $t->string('mime_type', 120);
            $t->unsignedBigInteger('size');
            $t->string('sha256', 64);
            $t->string('storage_key');
            $t->foreignId('uploaded_by_id')->constrained('users');
            $t->timestamps();
            $t->index(['assessment_id', 'kind']);
        });

        Schema::create('file_blobs', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->binary('data');
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignUlid('assessment_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('message');
            $t->boolean('read')->default(false);
            $t->timestamps();
            $t->index(['user_id', 'read']);
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'file_blobs', 'attachments', 'audit_logs', 'signatures', 'moderation_records', 'assessments', 'subjects', 'sessions', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
