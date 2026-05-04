<?php

namespace App\Http\Requests\Admin\References;

use App\Enums\DegreeProgram;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgramOfferingUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * TODO (Phase 7): re-validate that narrowing min_level/max_level does not
     * orphan existing LevelCredentialRequirement rows whose `level` falls
     * outside the new range. Phase 7's ApplicationStoreRequest will re-check
     * the range from the offering, so the inconsistency stays contained until
     * then.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $offering = $this->route('program_offering');
        $id = $offering?->id;
        $departmentId = $this->input('department_id', $offering?->department_id);

        return [
            'department_id' => [
                'required', 'integer',
                Rule::exists('departments', 'id')->whereNull('deleted_at'),
            ],
            'degree_program' => [
                'required',
                Rule::enum(DegreeProgram::class),
                Rule::unique('program_offerings', 'degree_program')
                    ->where(fn ($q) => $q->where('department_id', $departmentId))
                    ->ignore($id),
            ],
            'min_level' => ['required', 'integer', 'min:1', 'max:10'],
            'max_level' => ['required', 'integer', 'min:1', 'max:10', 'gte:min_level'],
        ];
    }
}
