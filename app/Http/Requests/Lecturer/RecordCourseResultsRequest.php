<?php

namespace App\Http\Requests\Lecturer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordCourseResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'results' => ['present', 'array'],
            'results.*.student_profile_id' => ['required', 'integer'],
            'results.*.ca_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'results.*.exam_score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * The validated result rows.
     *
     * @return array<int, array{student_profile_id: int, ca_score: int|null, exam_score: int|null}>
     */
    public function rows(): array
    {
        return $this->validated('results', []);
    }
}
