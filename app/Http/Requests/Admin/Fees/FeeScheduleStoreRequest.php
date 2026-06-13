<?php

namespace App\Http\Requests\Admin\Fees;

use App\Http\Requests\Admin\Fees\Concerns\ValidatesFeeSchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeeScheduleStoreRequest extends FormRequest
{
    use ValidatesFeeSchedule;

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $rules = $this->feeFieldRules();

        $rules['academic_year'][] = Rule::unique('fee_schedules', 'academic_year')
            ->where(fn ($q) => $q
                ->where('program_offering_id', $this->integer('program_offering_id'))
                ->where('level', $this->integer('level')));

        return $rules;
    }
}
