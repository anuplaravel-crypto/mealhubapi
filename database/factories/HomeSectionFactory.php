<?php

namespace Database\Factories;

use App\Models\HomeSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeSection>
 */
class HomeSectionFactory extends Factory
{
    /**
     * `key` is unique-indexed, so the default must be generated rather than
     * fixed — a literal would collide on the second create() in a test.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'eyebrow' => fake()->words(2, true),
            'heading' => fake()->sentence(6),
            'heading_accent' => fake()->words(3, true),
            'body' => fake()->paragraph(),
            'image' => null,
            'image_url' => fake()->imageUrl(800, 600),
            'extras' => null,
            'is_published' => true,
        ];
    }

    /**
     * Pin the row to one of the real section keys the client renders.
     */
    public function key(string $key): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => $key,
        ]);
    }

    /**
     * @param  array<string, string>  $extras
     */
    public function withExtras(array $extras): static
    {
        return $this->state(fn (array $attributes) => [
            'extras' => $extras,
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
