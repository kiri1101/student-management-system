<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ordered payment milestones for a fee schedule. Children belong wholly to
     * their schedule and are synced as a set when the schedule is edited, so
     * they cascade on hard-delete and carry no soft-delete column of their own.
     */
    public function up(): void
    {
        Schema::create('fee_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('label');
            $table->unsignedInteger('amount_xaf');
            $table->date('due_date');
            $table->timestamps();

            $table->unique(['fee_schedule_id', 'sequence'], 'fee_installment_schedule_sequence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_installments');
    }
};
