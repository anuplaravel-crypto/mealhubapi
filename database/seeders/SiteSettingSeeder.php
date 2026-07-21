<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * The branding the site shipped with, transcribed verbatim from the markup it
 * replaced so seeding produces a visually identical page.
 *
 * `meta_title` is what the layout used to render from
 * config('app.name') . ' — Fresh Meals, Delivered with Care'; the separator is
 * an em-dash, not a hyphen.
 */
class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::firstOrCreate(['id' => SiteSetting::SINGLETON_ID], [
            'site_name' => 'MealHub',
            'brand_primary_text' => 'Meal',
            'brand_accent_text' => 'Hub',
            'meta_title' => 'MealHub — Fresh Meals, Delivered with Care',
            'meta_description' => 'Personalised meal plans from trusted local restaurants, delivered fresh by friendly riders.',
            'logo' => null,
            'footer_blurb' => 'Personalised meal plans from trusted local restaurants, delivered fresh by friendly riders. Eat better, live better.',
        ]);
    }
}
