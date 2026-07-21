<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * A manifest, not an implementation — every seeder below owns its own data and
 * is idempotent (firstOrCreate on a natural key), so this is safe to re-run.
 *
 * DevUserSeeder is last and is the only entry that creates accounts; it no-ops
 * in production.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            LocationSeeder::class,
            SiteSettingSeeder::class,
            HomeStatSeeder::class,
            NavMenuSeeder::class,
            HomeSectionSeeder::class,
            MealCategorySeeder::class,
            FeaturedRestaurantSeeder::class,
            TestimonialSeeder::class,
            DevUserSeeder::class,
        ]);
    }
}
