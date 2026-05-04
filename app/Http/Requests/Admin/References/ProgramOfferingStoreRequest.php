<?php

namespace App\Http\Requests\Admin\References;

use App\Enums\DegreeProgram;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgramOfferingStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'department_id' => [
                'required', 'integer',
                Rule::exists('departments', 'id')->whereNull('deleted_at'),
            ],
            'degree_program' => [
                'required',
                Rule::enum(DegreeProgram::class),
                Rule::unique('program_offerings', 'degree_program')
                    ->where(fn ($q) => $q->where('department_id', $this->input('department_id'))),
            ],
            'min_level' => ['required', 'integer', 'min:1', 'max:10'],
            'max_level' => ['required', 'integer', 'min:1', 'max:10', 'gte:min_level'],
        ];
    }
}
