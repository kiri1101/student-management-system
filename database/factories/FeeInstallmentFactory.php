<?php

namespace Database\Factories;

use App\Models\FeeInstallment;
use App\Models\FeeSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeInstallment>
 */
class FeeInstallmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fee_schedule_id' => FeeSchedule::factory(),
            'sequence' => 1,
            'label' => fake()->randomElement(['First installment', 'Second installment', 'Final installment']),
            'amount_xaf' => fake()->numberBetween(25, 200) * 1000,
            'due_date' => fake()->dateTimeBetween('+1 month', '+10 months')->format('Y-m-d'),
        ];
    }
}
