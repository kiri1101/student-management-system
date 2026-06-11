<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per admission year backing matricule generation. Written through
     * the query builder only (no Eloquent model): no timestamps, no soft
     * deletes — the row is a counter, not domain data. Rows are seeded lazily
     * by StudentProfile::nextMatriculeForYear() so force-deleted profiles can
     * never poison the next number (AUDIT.md AUD-006).
     */
    public function up(): void
    {
        Schema::create('matricule_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matricule_sequences');
    }
};
