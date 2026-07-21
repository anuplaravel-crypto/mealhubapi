<?php

namespace Database\Factories;

use App\Models\HomeSection;
use App\Models\SectionFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionFeature>
 */
class SectionFeatureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'home_section_id' => HomeSection::factory(),
            'title' => fake()->words(3, true),
            'body' => fake()->sentence(12),
            'icon_class' => 'bi bi-check-circle',
            'accent' => 'green',
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    public function forSection(HomeSection $section): static
    {
        return $this->state(fn (array $attributes) => [
            'home_section_id' => $section->id,
        ]);
    }

    public function orange(): static
    {
        return $this->state(fn (array $attributes) => [
            'accent' => 'orange',
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
