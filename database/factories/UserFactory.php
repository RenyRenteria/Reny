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
            'phone' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'fan',
            'royal_status' => 'open',
            'royal_ends_at' => null,
            'remember_token' => Str::random(10),
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

    public function royal(): static
    {
        return $this->state(fn (array $attributes) => [
            'royal_status' => 'royal_active',
            'royal_ends_at' => now()->addMonth(),
        ]);
    }

    public function royalGrace(): static
    {
        return $this->state(fn (array $attributes) => [
            'royal_status' => 'royal_grace',
            'royal_ends_at' => now()->addWeek(),
        ]);
    }

    public function expiredRoyal(): static
    {
        return $this->state(fn (array $attributes) => [
            'royal_status' => 'royal_expired',
            'royal_ends_at' => now()->subDay(),
        ]);
    }

    public function cancelledRoyal(): static
    {
        return $this->state(fn (array $attributes) => [
            'royal_status' => 'cancelled',
            'royal_ends_at' => now()->addMonth(),
        ]);
    }

    public function onHoldRoyal(): static
    {
        return $this->state(fn (array $attributes) => [
            'royal_status' => 'on_hold',
            'royal_ends_at' => now()->addMonth(),
        ]);
    }

    public function paymentFailedRoyal(): static
    {
        return $this->state(fn (array $attributes) => [
            'royal_status' => 'payment_failed',
            'royal_ends_at' => now()->addMonth(),
        ]);
    }

    public function refundedRoyal(): static
    {
        return $this->state(fn (array $attributes) => [
            'royal_status' => 'refunded',
            'royal_ends_at' => now()->addMonth(),
        ]);
    }
}
