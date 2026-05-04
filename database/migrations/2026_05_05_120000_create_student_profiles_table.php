<?php

use App\Enums\StudentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('matricule')->unique();
            $table->foreignId('program_offering_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('academic_year');
            $table->date('enrolled_at');
            $table->string('status')->default(StudentStatus::Active->value);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
