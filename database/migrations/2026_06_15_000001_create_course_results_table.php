<?php

use App\Enums\ResultStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One result row per (course, student). The ca_score and exam_score are
     * percentage marks (0–100); the weighted final score and letter grade are
     * computed in the model and never persisted. A draft is editable by the
     * lecturer; once published by the SAO it is locked.
     */
    public function up(): void
    {
        Schema::create('course_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('ca_score')->nullable();
            $table->unsignedSmallInteger('exam_score')->nullable();
            $table->string('status')->default(ResultStatus::Draft->value);
            $table->dateTime('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // One result per student per course.
            $table->unique(['course_id', 'student_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_results');
    }
};
