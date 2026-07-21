<?php

namespace Database\Seeders;

use App\Models\NavMenu;
use Illuminate\Database\Seeder;

/**
 * The navigation the public site shipped with.
 *
 * Three links target an SPA `route_key` rather than a URL: "Sign in",
 * "Get started" and "Add your restaurant". The key is an opaque token the
 * client maps to its own path — this API has no named routes to resolve.
 * "Become a rider" is *not* among them: it points at `#riders`, the section
 * that pitches the role before the form, and retargeting it would drop that
 * step.
 *
 * "Business", "Help center" and the four social icons stay `#`: no real target
 * exists yet, and an admin can fill them in from the CMS screen.
 */
class NavMenuSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_merge($this->navbar(), $this->footerMenu(), $this->social(), $this->legal()) as $link) {
            NavMenu::firstOrCreate(
                ['location' => $link['location'], 'label' => $link['label']],
                $link + ['is_published' => true],
            );
        }
    }

    /**
     * The six nav links, then the two CTA buttons.
     *
     * @return list<array<string, mixed>>
     */
    private function navbar(): array
    {
        $links = [
            ['label' => 'Home', 'url' => '#home'],
            ['label' => 'About', 'url' => '#about'],
            ['label' => 'How it works', 'url' => '#how'],
            ['label' => 'Restaurants', 'url' => '#restaurants'],
            ['label' => 'Delivery', 'url' => '#riders'],
            ['label' => 'Reviews', 'url' => '#reviews'],
        ];

        $rows = [];

        foreach ($links as $index => $link) {
            $rows[] = $link + [
                'location' => NavMenu::LOCATION_NAVBAR,
                'sort_order' => $index + 1,
            ];
        }

        $rows[] = [
            'location' => NavMenu::LOCATION_NAVBAR,
            'label' => 'Sign in',
            'variant' => NavMenu::VARIANT_OUTLINE,
            'route_key' => 'login',
            'sort_order' => 7,
        ];

        $rows[] = [
            'location' => NavMenu::LOCATION_NAVBAR,
            'label' => 'Get started',
            'variant' => NavMenu::VARIANT_SOLID,
            'route_key' => 'register',
            'sort_order' => 8,
        ];

        return $rows;
    }

    /**
     * Both footer columns, on one continuous sort_order — Company 1-4 then
     * Partners 5-8, which is what keeps the columns in that order without a
     * separate group-ordering column.
     *
     * @return list<array<string, mixed>>
     */
    private function footerMenu(): array
    {
        $columns = [
            'Company' => [
                ['label' => 'About us', 'url' => '#about'],
                ['label' => 'How it works', 'url' => '#how'],
                ['label' => 'Restaurants', 'url' => '#restaurants'],
                ['label' => 'Reviews', 'url' => '#reviews'],
            ],
            'Partners' => [
                ['label' => 'Add your restaurant', 'route_key' => 'restaurant.register'],
                ['label' => 'Become a rider', 'url' => '#riders'],
                ['label' => 'Business', 'url' => '#'],
                ['label' => 'Help center', 'url' => '#'],
            ],
        ];

        $rows = [];
        $order = 1;

        foreach ($columns as $groupLabel => $links) {
            foreach ($links as $link) {
                $rows[] = $link + [
                    'location' => NavMenu::LOCATION_FOOTER_MENU,
                    'group_label' => $groupLabel,
                    'sort_order' => $order++,
                ];
            }
        }

        return $rows;
    }

    /**
     * The social row renders only its icons — the label is admin-facing.
     *
     * @return list<array<string, mixed>>
     */
    private function social(): array
    {
        $icons = [
            'Facebook' => 'bi bi-facebook',
            'Instagram' => 'bi bi-instagram',
            'X' => 'bi bi-twitter-x',
            'LinkedIn' => 'bi bi-linkedin',
        ];

        $rows = [];
        $order = 1;

        foreach ($icons as $label => $iconClass) {
            $rows[] = [
                'location' => NavMenu::LOCATION_SOCIAL,
                'label' => $label,
                'icon_class' => $iconClass,
                'url' => '#',
                'sort_order' => $order++,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function legal(): array
    {
        $rows = [];
        $order = 1;

        foreach (['Privacy', 'Terms', 'Cookies'] as $label) {
            $rows[] = [
                'location' => NavMenu::LOCATION_LEGAL,
                'label' => $label,
                'url' => '#',
                'sort_order' => $order++,
            ];
        }

        return $rows;
    }
}
