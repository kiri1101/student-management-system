<?php

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per bank deposit a student reports. The uploaded bank slip lives
     * on the default disk (slip_path); the accountant validates against it and
     * the row carries the reviewer + terminal status. `academic_year` is
     * snapshotted from the student's enrollment at submission so the
     * validated-paid total stays attributable to a year even after the student
     * advances (the figure #8 will read).
     */
    public function up(): void
    {
        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained()->restrictOnDelete();
            $table->string('academic_year');
            $table->string('bank');
            $table->unsignedInteger('amount_xaf');
            $table->string('bank_reference');
            $table->string('slip_path');
            $table->string('slip_original_filename');
            $table->string('slip_mime_type');
            $table->string('status')->default(PaymentStatus::Submitted->value);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Accountant queue: WHERE status = ? ORDER BY created_at.
            $table->index(['status', 'created_at']);
            // Student running total + list: WHERE student_profile_id = ? [AND status].
            $table->index(['student_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_submissions');
    }
};
