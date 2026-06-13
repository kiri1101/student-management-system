<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->json('changes')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');

            // Composite indexes match the audit-log modal's filter +
            // ORDER BY occurred_at, id patterns (AUDIT.md AUD-008); the
            // (user_id, occurred_at) pair also serves the user_id FK and
            // nullableMorphs() already indexes (subject_type, subject_id).
            $table->index(['occurred_at', 'id']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
            $table->index(['subject_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
