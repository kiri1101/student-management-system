<?php

namespace Database\Factories;

use App\Models\AccountantProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountantProfile>
 */
class AccountantProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank_desk' => 'UBA',
            'cashier_window' => 'A1',
        ];
    }
}
