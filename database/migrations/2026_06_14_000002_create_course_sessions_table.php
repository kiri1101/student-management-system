<?php

use App\Enums\SessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A scheduled teaching session for an Approved course. The assigned lecturer
     * creates sessions and marks cohort attendance against them. Cancelling a
     * session soft-cancels it (status flip), it is never hard-deleted.
     */
    public function up(): void
    {
        Schema::create('course_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_for');
            $table->string('topic');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('status')->default(SessionStatus::Scheduled->value);
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // A course's session timeline (chronological listing).
            $table->index(['course_id', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_sessions');
    }
};
