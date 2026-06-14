<?php

namespace App\Http\Requests\Accountant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectDeferralRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'decision_notes' => ['required', 'string', 'max:500'],
        ];
    }
}
