<?php

namespace Database\Seeders;

use App\Models\FeaturedRestaurant;
use Illuminate\Database\Seeder;

/**
 * The six carousel cards the home page shipped with, copied verbatim so
 * seeding produces a visually identical page — two slides of three.
 *
 * Every row leaves `user_id` null: these are placeholders for restaurants
 * that do not exist as domain entities yet, not records of real partners. The
 * ratings in particular are invented marketing copy carried over from the
 * static mockup, not anything a customer left. See the table's migration.
 *
 * The delivery times use en-dashes (25–30), matching the static markup — a
 * hyphen here would be a visible change.
 */
class FeaturedRestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $cards = [
            [
                'name' => 'Green Garden Kitchen',
                'image_url' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.9,
                'location' => 'Downtown',
                'cuisines' => 'Salads, Bowls',
                'delivery_time' => '25–30 min',
                'tag' => 'Top rated',
                'perk_label' => 'Free delivery',
                'perk_variant' => 'success',
                'sort_order' => 1,
            ],
            [
                'name' => 'Protein House',
                'image_url' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.7,
                'location' => 'Riverside',
                'cuisines' => 'Grills, High Protein',
                'delivery_time' => '30–35 min',
                'perk_label' => 'Popular',
                'perk_variant' => 'warning',
                'sort_order' => 2,
            ],
            [
                'name' => 'Urban Vegan',
                'image_url' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.8,
                'location' => 'Old Town',
                'cuisines' => 'Vegan, Plant-based',
                'delivery_time' => '20–25 min',
                'tag' => 'New',
                'perk_label' => 'Free delivery',
                'perk_variant' => 'success',
                'sort_order' => 3,
            ],
            [
                'name' => 'Bella Italia',
                'image_url' => 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.6,
                'location' => 'Midtown',
                'cuisines' => 'Pasta, Healthy Italian',
                'delivery_time' => '28–34 min',
                'perk_label' => 'Free delivery',
                'perk_variant' => 'success',
                'sort_order' => 4,
            ],
            [
                'name' => 'Sushi Zen',
                'image_url' => 'https://images.unsplash.com/photo-1473093295043-cdd812d0e601?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.9,
                'location' => 'Harbour',
                'cuisines' => 'Japanese, Low-cal',
                'delivery_time' => '32–40 min',
                'tag' => 'Top rated',
                'perk_label' => 'Popular',
                'perk_variant' => 'warning',
                'sort_order' => 5,
            ],
            [
                'name' => 'Fresh & Co.',
                'image_url' => 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.7,
                'location' => 'Hillside',
                'cuisines' => 'Salads, Smoothies',
                'delivery_time' => '18–24 min',
                'perk_label' => 'Free delivery',
                'perk_variant' => 'success',
                'sort_order' => 6,
            ],
        ];

        foreach ($cards as $card) {
            FeaturedRestaurant::firstOrCreate(
                ['name' => $card['name']],
                $card + ['is_published' => true, 'user_id' => null],
            );
        }
    }
}
