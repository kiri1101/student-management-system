<?php

namespace App\Http\Requests\Admin\Fees;

use App\Http\Requests\Admin\Fees\Concerns\ValidatesFeeSchedule;
use App\Models\FeeSchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeeScheduleUpdateRequest extends FormRequest
{
    use ValidatesFeeSchedule;

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $schedule = $this->route('fee_schedule');
        $id = $schedule instanceof FeeSchedule ? $schedule->id : null;

        $rules = $this->feeFieldRules();

        $rules['academic_year'][] = Rule::unique('fee_schedules', 'academic_year')
            ->where(fn ($q) => $q
                ->where('program_offering_id', $this->integer('program_offering_id'))
                ->where('level', $this->integer('level')))
            ->ignore($id);

        return $rules;
    }
}
