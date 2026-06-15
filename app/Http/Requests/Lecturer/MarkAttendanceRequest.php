<?php

namespace App\Http\Requests\Lecturer;

use App\Enums\AttendanceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkAttendanceRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array'],
            'records.*.student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'records.*.status' => ['required', Rule::in(array_column(AttendanceStatus::cases(), 'value'))],
        ];
    }

    /**
     * Flatten the validated records into a student_profile_id => status map for
     * the MarkAttendance action.
     *
     * @return array<int, string>
     */
    public function statuses(): array
    {
        $statuses = [];

        /** @var array<int, array{student_profile_id: int, status: string}> $records */
        $records = $this->validated('records');

        foreach ($records as $record) {
            $statuses[(int) $record['student_profile_id']] = $record['status'];
        }

        return $statuses;
    }
}
