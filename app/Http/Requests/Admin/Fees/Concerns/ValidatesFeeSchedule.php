<?php

namespace App\Http\Requests\Admin\Fees\Concerns;

use App\Models\ProgramOffering;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared field rules and cross-field checks for creating/updating a fee
 * schedule together with its installment set. The only difference between the
 * store and update requests is the schedule uniqueness rule, so that lives in
 * each request; everything else is shared here.
 */
trait ValidatesFeeSchedule
{
    /**
     * Field rules common to store and update (the composite-unique rule on the
     * schedule itself is supplied by each request).
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function feeFieldRules(): array
    {
        return [
            'program_offering_id' => [
                'required', 'integer',
                Rule::exists('program_offerings', 'id')->whereNull('deleted_at'),
            ],
            'level' => ['required', 'integer', 'min:1', 'max:10'],
            'academic_year' => ['required', 'string', 'regex:/^\d{4}$/'],
            'total_xaf' => ['required', 'integer', 'min:1'],

            'installments' => ['present', 'array'],
            'installments.*.sequence' => ['required', 'integer', 'min:1', 'distinct'],
            'installments.*.label' => ['required', 'string', 'max:255'],
            'installments.*.amount_xaf' => ['required', 'integer', 'min:1'],
            'installments.*.due_date' => ['required', 'date'],
        ];
    }

    /**
     * Cross-field checks that only make sense once the per-field rules pass:
     * the chosen level must sit inside the offering's declared range, the
     * installments must not exceed the schedule total, and their due dates must
     * increase with their sequence.
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

                $this->validateLevelWithinOffering($validator);
                $this->validateInstallmentTotal($validator);
                $this->validateInstallmentDueDates($validator);
            },
        ];
    }

    private function validateLevelWithinOffering(Validator $validator): void
    {
        $offering = ProgramOffering::find($this->integer('program_offering_id'));

        if (! $offering instanceof ProgramOffering) {
            return;
        }

        $level = $this->integer('level');

        if ($level < $offering->min_level || $level > $offering->max_level) {
            $validator->errors()->add('level', __('Level must be between :min and :max for this offering.', [
                'min' => $offering->min_level,
                'max' => $offering->max_level,
            ]));
        }
    }

    private function validateInstallmentTotal(Validator $validator): void
    {
        $installments = $this->collect('installments');

        $sum = $installments->sum(fn ($row): int => (int) ($row['amount_xaf'] ?? 0));

        if ($sum > $this->integer('total_xaf')) {
            $validator->errors()->add('installments', __('Installments total (:sum XAF) exceeds the schedule total (:total XAF).', [
                'sum' => number_format($sum),
                'total' => number_format($this->integer('total_xaf')),
            ]));
        }
    }

    private function validateInstallmentDueDates(Validator $validator): void
    {
        $rows = $this->collect('installments')
            ->sortBy(fn ($row): int => (int) ($row['sequence'] ?? 0))
            ->values();

        $previous = null;
        foreach ($rows as $row) {
            $dueDate = strtotime((string) ($row['due_date'] ?? ''));

            if ($previous !== null && $dueDate !== false && $dueDate <= $previous) {
                $validator->errors()->add('installments', __('Each installment must be due after the one before it.'));

                return;
            }

            if ($dueDate !== false) {
                $previous = $dueDate;
            }
        }
    }
}
