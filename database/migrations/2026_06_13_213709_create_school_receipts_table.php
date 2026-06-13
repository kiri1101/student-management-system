<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The single source of truth that a validated payment exists. Exactly one
     * per validated submission (unique FK), immutable once written (the model
     * blocks update/delete like AuditLog), and carries an HMAC `signature` so
     * the public verify endpoint can prove authenticity and bound identity.
     */
    public function up(): void
    {
        Schema::create('school_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_submission_id')->unique()->constrained()->restrictOnDelete();
            $table->string('receipt_number')->unique();
            $table->unsignedInteger('amount_xaf');
            $table->string('signature');
            $table->timestamp('issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_receipts');
    }
};
