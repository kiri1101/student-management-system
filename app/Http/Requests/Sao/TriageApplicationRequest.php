<?php

namespace App\Http\Requests\Sao;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TriageApplicationRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $allowed = array_map(
            fn (ApplicationStatus $status): string => $status->value,
            Application::INTERIM_STATUSES,
        );

        return [
            'status' => ['required', 'string', Rule::in($allowed)],
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
