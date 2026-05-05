<?php

namespace App\Http\Requests\Sao;

use App\Enums\ApplicationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TriageApplicationRequest extends FormRequest
{
    /**
     * Reversible interim statuses owned by the SAO triage flow.
     *
     * @var list<string>
     */
    private const ALLOWED_STATUSES = [
        'submitted',
        'under_review',
        'documents_requested',
    ];

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(self::ALLOWED_STATUSES)],
            'notes' => [
                Rule::requiredIf(fn (): bool => $this->input('status') === ApplicationStatus::DocumentsRequested->value),
                'nullable', 'string', 'max:5000',
            ],
        ];
    }

    public function statusEnum(): ApplicationStatus
    {
        return ApplicationStatus::from($this->string('status')->toString());
    }
}
