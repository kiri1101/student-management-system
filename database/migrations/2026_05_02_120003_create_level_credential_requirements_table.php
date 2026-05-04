<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_credential_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_offering_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('level');
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->boolean('required')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['program_offering_id', 'level', 'document_type_id'], 'lcr_offering_level_doctype_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_credential_requirements');
    }
};
