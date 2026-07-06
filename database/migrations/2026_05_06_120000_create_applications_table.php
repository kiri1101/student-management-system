<?php

use App\Enums\ApplicationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_offering_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('contact_email');
            $table->string('phone');
            $table->date('date_of_birth');
            $table->string('previous_institute')->nullable();
            $table->string('status')->default(ApplicationStatus::Submitted->value);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // SAO queue: WHERE status IN (...) ORDER BY submitted_at
            // (AUDIT.md AUD-019); the (user_id, status) pair serves the
            // one-open-application guard and the user_id FK.
            $table->index(['status', 'submitted_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
