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
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            // Not fake()->phoneNumber(): it emits formatted strings well over
            // the column's 20 chars ("+1-555-123-4567 x8901"). SQLite ignores
            // varchar limits so tests would pass, then the same factory would
            // throw "Data truncated" against MySQL.
            'mobile' => fake()->numerify('01#########'),
            'role' => 'customer',
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'accept_registration_tnc' => true,
            'marketing_consent' => fake()->boolean(),
            'otp' => (string) fake()->numberBetween(100000, 999999),
            'otp_expires_at' => now()->addMinutes(10),
            'status' => true,
            'is_email_verified' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_email_verified' => false,
        ]);
    }

    /**
     * Awaiting admin approval — registered and verified, but still gated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }

    /**
     * An OTP that is past its window, for exercising expiry paths.
     */
    public function withExpiredOtp(): static
    {
        return $this->state(fn (array $attributes) => [
            'otp_expires_at' => now()->subMinute(),
        ]);
    }

    public function admin(): static
    {
        return $this->role('admin');
    }

    public function customer(): static
    {
        return $this->role('customer');
    }

    public function restaurant(): static
    {
        return $this->role('restaurant');
    }

    public function rider(): static
    {
        return $this->role('rider');
    }

    /**
     * Every auth lookup is scoped by role, so tests almost always need a user
     * of a specific one — hence the four named states above rather than
     * inline ['role' => ...] overrides at each call site.
     */
    private function role(string $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ]);
    }
}
