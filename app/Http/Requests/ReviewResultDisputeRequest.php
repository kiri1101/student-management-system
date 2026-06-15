<?php

namespace App\Http\Requests;

use App\Enums\DisputeStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewResultDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                DisputeStatus::UnderReview->value,
                DisputeStatus::Resolved->value,
                DisputeStatus::Rejected->value,
            ])],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function disputeStatus(): DisputeStatus
    {
        return DisputeStatus::from($this->string('status')->toString());
    }

    public function resolutionNotes(): ?string
    {
        $notes = $this->input('resolution_notes');

        return $notes === null ? null : (string) $notes;
    }
}
