<?php

namespace App\Http\Requests\Sao;

use App\Rules\LevelWithinOfferingRange;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateCourseRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'program_offering_id' => ['required', 'integer', 'exists:program_offerings,id'],
            'level' => ['required', 'integer', new LevelWithinOfferingRange],
            'academic_year' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'max:50', $this->uniqueCodeRule()],
            'title' => ['required', 'string', 'max:255'],
            'credits' => ['required', 'integer', 'min:1', 'max:60'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'description' => ['nullable', 'string', 'max:5000'],
            'lecturer_profile_id' => ['nullable', 'integer', 'exists:lecturer_profiles,id'],
        ];
    }

    /**
     * Unique course code within its (offering, level, academic year) cohort,
     * ignoring the course being edited (AUDIT.md AUD-017). Spans soft-deleted
     * rows, matching the composite DB index.
     */
    private function uniqueCodeRule(): Unique
    {
        return Rule::unique('courses', 'code')
            ->ignore($this->route('course'))
            ->where(fn ($query) => $query
                ->where('program_offering_id', $this->integer('program_offering_id'))
                ->where('level', $this->integer('level'))
                ->where('academic_year', $this->string('academic_year')->toString()));
    }
}
