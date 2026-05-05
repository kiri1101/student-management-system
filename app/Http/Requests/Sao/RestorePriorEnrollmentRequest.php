<?php

namespace App\Http\Requests\Sao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestorePriorEnrollmentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'prior_profile_id' => [
                'required', 'integer',
                Rule::exists('student_profiles', 'id'),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
