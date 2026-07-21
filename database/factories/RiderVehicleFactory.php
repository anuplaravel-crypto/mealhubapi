<?php

namespace Database\Factories;

use App\Models\RiderVehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiderVehicle>
 */
class RiderVehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rider_id' => User::factory()->rider(),
            'image' => null,
            'vehicle_type' => fake()->randomElement(RiderVehicle::VEHICLE_TYPES),
            'registration_number' => strtoupper(fake()->bothify('??-##-####')),
            'vehicle_color' => fake()->safeColorName(),
            'vehicle_brand' => fake()->company(),
            'vehicle_model' => fake()->bothify('Model-###'),
            'is_active' => true,
        ];
    }

    /**
     * Submitted but not yet approved by an admin — mirrors the rider's
     * users.status still being false.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function bike(): static
    {
        return $this->vehicleType('bike');
    }

    public function car(): static
    {
        return $this->vehicleType('car');
    }

    public function scooter(): static
    {
        return $this->vehicleType('scooter');
    }

    public function bicycle(): static
    {
        return $this->vehicleType('bicycle');
    }

    private function vehicleType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'vehicle_type' => $type,
        ]);
    }
}
