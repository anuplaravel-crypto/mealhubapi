<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\County;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city(),
            'county_id' => County::factory(),
        ];
    }

    public function forCounty(County $county): static
    {
        return $this->state(fn (array $attributes) => [
            'county_id' => $county->id,
        ]);
    }
}
