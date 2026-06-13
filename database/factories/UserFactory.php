<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has a phone number usable as a login identifier.
     * Defaults to a canonical E.164-ish value (leading `+`, then digits).
     */
    public function withPhone(?string $phone = null): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => $phone ?? '+2376'.fake()->unique()->numerify('########'),
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the user is a staff member identified by an `employee_id`.
     *
     * `employee_id` stays out of `User::$fillable` until Phase 10 plumbs it
     * through an explicit Form Request — a `forceFill` here keeps tests honest
     * without widening the mass-assignment surface.
     */
    public function staff(?string $employeeId = null): static
    {
        return $this->afterCreating(function (User $user) use ($employeeId): void {
            $user->forceFill([
                'employee_id' => $employeeId ?? 'emp-'.fake()->unique()->numerify('####'),
            ])->saveQuietly();
        });
    }
}
