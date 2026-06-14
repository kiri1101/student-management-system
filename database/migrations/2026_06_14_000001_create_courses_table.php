<?php

use App\Enums\CoursePlanStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A course offered to a specific cohort — the (program_offering, level,
     * academic_year) tuple — within a semester. A lecturer may be assigned to
     * draft and submit its plan; SAO approves or rejects. Cohort membership is
     * implicit: the matching active StudentProfiles, no enrollment table.
     */
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_offering_id')->constrained();
            $table->unsignedSmallInteger('level');
            $table->string('academic_year');
            $table->string('code');
            $table->string('title');
            $table->unsignedSmallInteger('credits');
            $table->unsignedTinyInteger('semester');
            $table->text('description')->nullable();
            $table->foreignId('lecturer_profile_id')->nullable()->constrained();
            $table->string('plan_status')->default(CoursePlanStatus::Draft->value);
            $table->timestamp('plan_submitted_at')->nullable();
            $table->timestamp('plan_reviewed_at')->nullable();
            $table->foreignId('plan_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('plan_review_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // A course code is unique within a cohort offering + year.
            $table->unique(['program_offering_id', 'level', 'academic_year', 'code']);
            // Lecturer's "my courses" list.
            $table->index('lecturer_profile_id');
            // SAO review queue: WHERE plan_status = ?.
            $table->index('plan_status');
            // Cohort + catalog lookups.
            $table->index(['program_offering_id', 'level', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
