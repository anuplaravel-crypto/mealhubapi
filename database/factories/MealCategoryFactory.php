<?php

namespace Database\Factories;

use App\Models\MealCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealCategory>
 */
class MealCategoryFactory extends Factory
{
    /**
     * `name` is unique-indexed, so the default must be generated rather than
     * fixed — a literal would collide on the second create() in a test.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->unique()->words(2, true)),
            'tagline' => fake()->numberBetween(20, 300).'+ options',
            'image' => null,
            'image_url' => fake()->imageUrl(400, 300),
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
