<?php

namespace Database\Factories;

use App\Models\FeaturedRestaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeaturedRestaurant>
 */
class FeaturedRestaurantFactory extends Factory
{
    /**
     * `user_id` defaults to null, matching every seeded card: these are
     * placeholders until a real restaurant entity exists to link to.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->company(),
            'image' => null,
            'image_url' => fake()->imageUrl(600, 400),
            'rating' => fake()->randomElement([4.0, 4.3, 4.5, 4.8, 5.0]),
            'location' => fake()->city(),
            'cuisines' => implode(' • ', fake()->words(2)),
            'delivery_time' => fake()->numberBetween(15, 30).'–'.fake()->numberBetween(35, 60).' min',
            'tag' => fake()->randomElement(['Top rated', 'New', null]),
            'perk_label' => fake()->randomElement(['Free delivery', 'Popular']),
            'perk_variant' => fake()->randomElement(FeaturedRestaurant::PERK_VARIANTS),
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Link the card to a real account. Only a role='restaurant' user is valid
     * here — `users` is one table for all four roles, so nothing but the
     * write-path validation enforces that.
     */
    public function linkedTo(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user?->id ?? User::factory()->restaurant(),
        ]);
    }

    public function topRated(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => 5.0,
            'tag' => 'Top rated',
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
