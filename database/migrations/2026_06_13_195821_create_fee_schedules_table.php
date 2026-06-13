<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_offering_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('academic_year');
            $table->unsignedInteger('total_xaf');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['program_offering_id', 'level', 'academic_year'], 'fee_schedule_offering_level_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_schedules');
    }
};
