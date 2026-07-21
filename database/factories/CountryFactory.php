<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->country(),
        ];
    }

    /**
     * A country with its counties (and each county's cities) already attached —
     * the whole cascade in one call, for tests that walk all three levels.
     */
    public function withCascade(int $counties = 2, int $cities = 3): static
    {
        return $this->has(
            CountyFactory::new()->has(CityFactory::new()->count($cities), 'cities')->count($counties),
            'counties',
        );
    }
}
