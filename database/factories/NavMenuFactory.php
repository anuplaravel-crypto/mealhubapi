<?php

namespace Database\Factories;

use App\Models\NavMenu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NavMenu>
 */
class NavMenuFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location' => NavMenu::LOCATION_NAVBAR,
            'group_label' => null,
            'label' => fake()->words(2, true),
            'icon_class' => null,
            'variant' => null,
            'url' => '#'.fake()->slug(1),
            'route_key' => null,
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    public function navbar(): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => NavMenu::LOCATION_NAVBAR,
            'group_label' => null,
        ]);
    }

    /**
     * A footer link. `group_label` is the column heading, so it is what splits
     * the single footer_menu location into two rendered columns.
     */
    public function footerMenu(string $group = 'Company'): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => NavMenu::LOCATION_FOOTER_MENU,
            'group_label' => $group,
        ]);
    }

    public function social(): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => NavMenu::LOCATION_SOCIAL,
            'icon_class' => 'bi bi-facebook',
        ]);
    }

    public function legal(): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => NavMenu::LOCATION_LEGAL,
        ]);
    }

    /**
     * A navbar call-to-action button rather than a plain link.
     */
    public function cta(string $variant = NavMenu::VARIANT_SOLID): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => NavMenu::LOCATION_NAVBAR,
            'variant' => $variant,
        ]);
    }

    /**
     * Targets an SPA route key instead of a literal URL. The two are mutually
     * exclusive, so this clears `url`.
     */
    public function withRouteKey(string $key = 'login'): static
    {
        return $this->state(fn (array $attributes) => [
            'route_key' => $key,
            'url' => null,
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
