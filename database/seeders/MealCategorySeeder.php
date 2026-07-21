<?php

namespace Database\Seeders;

use App\Models\MealCategory;
use Illuminate\Database\Seeder;

/**
 * The four meal-type cards the home page shipped with, copied verbatim so
 * seeding produces a visually identical page.
 *
 * The photos are hot-linked Unsplash images that a seeder cannot download —
 * they go into `image_url` rather than `image`, exactly as the testimonial
 * and home-section seeders do for the same reason.
 */
class MealCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Healthy Bowls',
                'tagline' => '120+ options',
                'image_url' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=500&q=80',
                'sort_order' => 1,
            ],
            [
                'name' => 'Breakfast',
                'tagline' => '80+ options',
                'image_url' => 'https://images.unsplash.com/photo-1432139555190-58524dae6a55?auto=format&fit=crop&w=500&q=80',
                'sort_order' => 2,
            ],
            [
                'name' => 'High Protein',
                'tagline' => '95+ options',
                'image_url' => 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?auto=format&fit=crop&w=500&q=80',
                'sort_order' => 3,
            ],
            [
                'name' => 'Vegan & Veggie',
                'tagline' => '110+ options',
                'image_url' => 'https://images.unsplash.com/photo-1551782450-a2132b4ba21d?auto=format&fit=crop&w=500&q=80',
                'sort_order' => 4,
            ],
        ];

        foreach ($categories as $category) {
            MealCategory::firstOrCreate(
                ['name' => $category['name']],
                $category + ['is_published' => true],
            );
        }
    }
}
