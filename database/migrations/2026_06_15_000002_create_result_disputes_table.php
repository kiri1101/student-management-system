<?php

use App\Enums\DisputeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A student-raised dispute against a published course result. At most one
     * non-terminal (Open/UnderReview) dispute per result is enforced at the
     * application layer, not by a unique index — resolved/rejected disputes
     * accumulate as a history.
     */
    public function up(): void
    {
        Schema::create('result_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->string('status')->default(DisputeStatus::Open->value);
            $table->text('resolution_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('course_result_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_disputes');
    }
};
