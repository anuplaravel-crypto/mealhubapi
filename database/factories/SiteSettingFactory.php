<?php

namespace Database\Factories;

use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SiteSetting>
 */
class SiteSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_name' => fake()->company(),
            'brand_primary_text' => fake()->word(),
            'brand_accent_text' => fake()->word(),
            'meta_title' => fake()->sentence(6),
            'meta_description' => fake()->sentence(14),
            'logo' => null,
            'footer_blurb' => fake()->sentence(18),
        ];
    }

    /**
     * An uploaded logo on file. The filename is opaque and random, matching
     * what the image service stores.
     */
    public function withLogo(): static
    {
        return $this->state(fn (array $attributes) => [
            'logo' => Str::random(40),
        ]);
    }
}
