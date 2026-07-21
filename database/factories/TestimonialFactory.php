<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote' => fake()->paragraph(),
            'author_name' => fake()->name(),
            'author_role' => fake()->jobTitle(),
            'avatar' => null,
            'avatar_url' => fake()->imageUrl(96, 96),
            'rating' => fake()->randomElement([4.0, 4.5, 5.0]),
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

    /**
     * An uploaded avatar. Clears avatar_url, matching the write path's rule
     * that at most one source survives an edit.
     */
    public function withUploadedAvatar(): static
    {
        return $this->state(fn (array $attributes) => [
            'avatar' => Str::random(40),
            'avatar_url' => null,
        ]);
    }

    public function withoutAvatar(): static
    {
        return $this->state(fn (array $attributes) => [
            'avatar' => null,
            'avatar_url' => null,
        ]);
    }
}
