<?php

namespace Database\Factories;

use App\Models\HomeStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeStat>
 */
class HomeStatFactory extends Factory
{
    /**
     * Defaults to the stat-bar shape, which is the stricter of the two.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'placement' => HomeStat::PLACEMENT_STAT_BAR,
            'label' => fake()->words(2, true),
            'value' => (string) fake()->numberBetween(100, 99999),
            'icon_class' => 'bi bi-shop',
            'accent' => fake()->randomElement(HomeStat::ACCENTS),
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A hero mini-stat: `value` is rendered verbatim, so it carries its own
     * suffix ("15k+", "4.9★"), and there is no icon or accent to show.
     */
    public function hero(): static
    {
        return $this->state(fn (array $attributes) => [
            'placement' => HomeStat::PLACEMENT_HERO,
            'value' => fake()->numberBetween(1, 99).'k+',
            'icon_class' => null,
        ]);
    }

    /**
     * A stat-bar card: `value` feeds a client-side counter animation, so it
     * must be digits only — "15k+" would animate to NaN.
     */
    public function statBar(): static
    {
        return $this->state(fn (array $attributes) => [
            'placement' => HomeStat::PLACEMENT_STAT_BAR,
            'value' => (string) fake()->numberBetween(100, 99999),
            'icon_class' => 'bi bi-shop',
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
