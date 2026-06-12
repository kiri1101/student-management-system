<?php

namespace App\Http\Requests\Admin\References;

use App\Enums\DegreeProgram;
use App\Models\Application;
use App\Models\ProgramOffering;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProgramOfferingUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
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

    /**
     * Narrowing min_level/max_level must not orphan existing credential
     * requirements or strand open applications at now-out-of-range levels;
     * widening is always allowed (AUDIT.md AUD-023).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $offering = $this->route('program_offering');

                if (! $offering instanceof ProgramOffering) {
                    return;
                }

                $min = (int) $this->input('min_level');
                $max = (int) $this->input('max_level');

                $outOfRange = fn ($query) => $query
                    ->where('level', '<', $min)
                    ->orWhere('level', '>', $max);

                $requirementLevels = $offering->levelCredentialRequirements()
                    ->where($outOfRange)
                    ->pluck('level');

                if ($requirementLevels->isNotEmpty()) {
                    $validator->errors()->add('min_level', __('Cannot narrow the range: credential requirements exist for level(s) :levels.', [
                        'levels' => $requirementLevels->unique()->sort()->implode(', '),
                    ]));
                }

                $applicationLevels = $offering->applications()
                    ->whereIn('status', Application::OPEN_STATUSES)
                    ->where($outOfRange)
                    ->pluck('level');

                if ($applicationLevels->isNotEmpty()) {
                    $validator->errors()->add('min_level', __('Cannot narrow the range: open applications exist for level(s) :levels.', [
                        'levels' => $applicationLevels->unique()->sort()->implode(', '),
                    ]));
                }
            },
        ];
    }
}
