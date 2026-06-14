<?php

namespace App\Http\Requests\Accountant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApproveDeferralRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'new_deadline' => ['required', 'date', 'after:today'],
            'decision_notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
