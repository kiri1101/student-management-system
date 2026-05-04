<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('degree_program');
            $table->unsignedTinyInteger('min_level');
            $table->unsignedTinyInteger('max_level');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['department_id', 'degree_program']);
            $table->index('degree_program');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_offerings');
    }
};
