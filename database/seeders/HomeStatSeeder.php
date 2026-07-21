<?php

namespace Database\Seeders;

use App\Models\HomeStat;
use Illuminate\Database\Seeder;

/**
 * The numbers the home page shipped with, copied verbatim so seeding produces
 * a visually identical page.
 *
 * The hero values carry their suffixes ("15k+", "4.9★") because that row is
 * printed as-is; the stat bar values are bare digits because the counter script
 * animates up to them and appends its own "+" above 1000.
 */
class HomeStatSeeder extends Seeder
{
    public function run(): void
    {
        $stats = [
            [
                'placement' => HomeStat::PLACEMENT_HERO,
                'label' => 'Restaurants',
                'value' => '250+',
                'sort_order' => 1,
            ],
            [
                'placement' => HomeStat::PLACEMENT_HERO,
                'label' => 'Meals delivered',
                'value' => '15k+',
                'sort_order' => 2,
            ],
            [
                'placement' => HomeStat::PLACEMENT_HERO,
                'label' => 'Avg. rating',
                'value' => '4.9★',
                'sort_order' => 3,
            ],
            [
                'placement' => HomeStat::PLACEMENT_STAT_BAR,
                'label' => 'Partner Restaurants',
                'value' => '250',
                'icon_class' => 'bi bi-shop',
                'accent' => 'green',
                'sort_order' => 1,
            ],
            [
                'placement' => HomeStat::PLACEMENT_STAT_BAR,
                'label' => 'Meals Delivered',
                'value' => '15000',
                'icon_class' => 'bi bi-bag-check',
                'accent' => 'orange',
                'sort_order' => 2,
            ],
            [
                'placement' => HomeStat::PLACEMENT_STAT_BAR,
                'label' => 'Active Riders',
                'value' => '120',
                'icon_class' => 'bi bi-bicycle',
                'accent' => 'green',
                'sort_order' => 3,
            ],
            [
                'placement' => HomeStat::PLACEMENT_STAT_BAR,
                'label' => 'Happy Customers',
                'value' => '9800',
                'icon_class' => 'bi bi-emoji-smile',
                'accent' => 'orange',
                'sort_order' => 4,
            ],
        ];

        foreach ($stats as $stat) {
            HomeStat::firstOrCreate(
                ['placement' => $stat['placement'], 'label' => $stat['label']],
                $stat + ['is_published' => true],
            );
        }
    }
}
