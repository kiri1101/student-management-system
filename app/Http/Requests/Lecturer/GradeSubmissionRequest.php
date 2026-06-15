<?php

namespace App\Http\Requests\Lecturer;

use App\Models\Assignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GradeSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The score upper bound is the route-bound assignment's max_score, so a grade
     * can never exceed the marks the assignment is worth.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var Assignment $assignment */
        $assignment = $this->route('assignment');

        return [
            'score' => ['required', 'integer', 'min:0', 'max:'.$assignment->max_score],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function feedback(): ?string
    {
        $feedback = $this->input('feedback');

        return $feedback === null ? null : (string) $feedback;
    }
}
