<?php

namespace App\Http\Requests\Sao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignLecturerRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'lecturer_profile_id' => ['required', 'integer', 'exists:lecturer_profiles,id'],
        ];
    }
}
