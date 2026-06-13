<?php

namespace App\Http\Requests\Sao;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideApplicationRequest extends FormRequest
{
    /**
     * Statuses that demand a decision-notes narrative.
     *
     * @var list<string>
     */
    private const NOTES_REQUIRED_FOR = [
        'rejected',
        'waitlisted',
    ];

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        // Terminal decisions an SAO may pick. `Withdrawn` is reserved for the
        // §13.4 reactivation merge flow and is not selectable here.
        $allowed = array_map(
            fn (ApplicationStatus $status): string => $status->value,
            array_values(array_filter(
                Application::TERMINAL_STATUSES,
                fn (ApplicationStatus $status): bool => $status !== ApplicationStatus::Withdrawn,
            )),
        );

        return [
            'status' => ['required', 'string', Rule::in($allowed)],
            'notes' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('status'), self::NOTES_REQUIRED_FOR, true)),
                'nullable', 'string', 'max:5000',
            ],
            'acknowledged_prior_history' => ['sometimes', 'boolean'],
        ];
    }

    public function statusEnum(): ApplicationStatus
    {
        return ApplicationStatus::from($this->string('status')->toString());
    }
}
