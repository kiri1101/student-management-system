<?php

namespace App\Rules;

use App\Models\ProgramOffering;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Ensures a `level` falls within the chosen offering's [min_level, max_level].
 * Bails silently when `program_offering_id` is missing or unknown so the
 * upstream `exists` rule surfaces its own error first.
 */
class LevelWithinOfferingRange implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $offeringId = $this->data['program_offering_id'] ?? null;

        if (! is_numeric($offeringId)) {
            return;
        }

        $offering = ProgramOffering::find($offeringId);

        if ($offering === null) {
            return;
        }

        if ($value < $offering->min_level || $value > $offering->max_level) {
            $fail(__('The :attribute must be between :min and :max for the selected program offering.', [
                'attribute' => $attribute,
                'min' => $offering->min_level,
                'max' => $offering->max_level,
            ]));
        }
    }
}
