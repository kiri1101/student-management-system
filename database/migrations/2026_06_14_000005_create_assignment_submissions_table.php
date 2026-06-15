<?php

use App\Enums\AssignmentSubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One submission per (assignment, student). Resubmitting before the deadline
     * replaces the stored file in place (the upserted row keeps the unique tuple
     * idempotent), and grading stamps the score/feedback/grader onto the same row.
     */
    public function up(): void
    {
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedInteger('size_bytes');
            $table->dateTime('submitted_at');
            $table->boolean('is_late')->default(false);
            $table->unsignedSmallInteger('score')->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users');
            $table->dateTime('graded_at')->nullable();
            $table->string('status')->default(AssignmentSubmissionStatus::Submitted->value);
            $table->timestamps();
            $table->softDeletes();

            // One submission per student per assignment.
            $table->unique(['assignment_id', 'student_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
