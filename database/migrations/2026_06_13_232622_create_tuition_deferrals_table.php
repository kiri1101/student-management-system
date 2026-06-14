<?php

use App\Enums\DeferralStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A student's request to defer tuition past an installment deadline. The
     * accountant approves (setting new_deadline — the date until which the gate
     * is lifted) or rejects. academic_year is snapshotted from the enrollment
     * so the standing engine can scope deferrals to the right year.
     */
    public function up(): void
    {
        Schema::create('tuition_deferrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained()->restrictOnDelete();
            $table->string('academic_year');
            $table->text('reason');
            $table->date('requested_new_deadline')->nullable();
            $table->string('status')->default(DeferralStatus::Requested->value);
            $table->date('new_deadline')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Accountant queue: WHERE status = ? ORDER BY created_at.
            $table->index(['status', 'created_at']);
            // Student's own list + the open-request guard + active-deferral lookup.
            $table->index(['student_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuition_deferrals');
    }
};
