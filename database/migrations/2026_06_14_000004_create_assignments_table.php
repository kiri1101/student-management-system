<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A graded assignment on an Approved course. The assigned lecturer creates
     * assignments, the cohort submits a single file each, and the lecturer grades
     * those submissions against the assignment's max_score. Soft-deletable so an
     * assignment can be retired without orphaning its submissions in the UI.
     */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions');
            $table->dateTime('due_at');
            $table->unsignedSmallInteger('max_score');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // A course's assignment listing.
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
