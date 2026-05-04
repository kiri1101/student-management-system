<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationDocument>
 */
class ApplicationDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'document_type_id' => fn () => DocumentType::firstOrCreate(
                ['code' => 'NID'],
                ['name' => 'National Identity'],
            )->id,
            'file_path' => 'applications/'.fake()->uuid().'.pdf',
            'original_filename' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1024, 1_048_576),
            'uploaded_at' => now(),
        ];
    }
}
