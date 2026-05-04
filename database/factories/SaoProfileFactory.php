<?php

namespace Database\Factories;

use App\Models\SaoProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaoProfile>
 */
class SaoProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'scope' => null,
        ];
    }
}
