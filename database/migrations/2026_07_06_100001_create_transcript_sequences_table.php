<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per academic year backing transcript-number generation. Query
     * builder only — a counter, not domain data. Mirrors receipt_sequences
     * (AUDIT.md AUD-006).
     */
    public function up(): void
    {
        Schema::create('transcript_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_sequences');
    }
};
