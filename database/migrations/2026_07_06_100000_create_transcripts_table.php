<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An immutable, HMAC-signed snapshot of a student's transcript at issue time
     * (#71). The `snapshot` JSON is the source of truth for the rendered PDF and
     * the public verify endpoint; `content_digest` drives dedupe. Immutable once
     * written (the model blocks update/delete like school_receipts).
     */
    public function up(): void
    {
        Schema::create('transcripts', function (Blueprint $table) {
            $table->id();
            $table->string('transcript_number')->unique();
            $table->foreignId('student_profile_id')->constrained()->restrictOnDelete();
            $table->string('matricule');
            $table->string('student_name')->nullable();
            $table->string('programme')->nullable();
            $table->unsignedInteger('level')->nullable();
            $table->json('snapshot');
            $table->string('content_digest', 64);
            $table->decimal('cgpa', 3, 2);
            $table->unsignedInteger('credits_earned');
            $table->unsignedInteger('credits_attempted');
            $table->string('signature');
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['student_profile_id', 'content_digest']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripts');
    }
};
