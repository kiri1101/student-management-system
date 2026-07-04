<?php

namespace Database\Factories;

use App\Enums\ApplicationDocumentStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentType;
use App\Models\User;
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
            'status' => ApplicationDocumentStatus::Pending->value,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => ApplicationDocumentStatus::Accepted->value,
            'review_notes' => null,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(?string $notes = null): static
    {
        return $this->state(fn (): array => [
            'status' => ApplicationDocumentStatus::Rejected->value,
            'review_notes' => $notes ?? 'Document is illegible.',
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
