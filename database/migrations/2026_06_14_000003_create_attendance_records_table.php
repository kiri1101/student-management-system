<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One attendance mark per (session, student). Upserted by the lecturer when
     * marking a session's cohort, so the unique tuple keeps re-marking idempotent.
     */
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained();
            $table->string('status');
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('marked_at');
            $table->timestamps();
            $table->softDeletes();

            // One mark per student per session.
            $table->unique(['course_session_id', 'student_profile_id']);
            // A student's attendance history across sessions.
            $table->index('student_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
