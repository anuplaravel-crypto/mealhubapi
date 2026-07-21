<?php

namespace Database\Seeders;

use App\Models\HomeSection;
use Illuminate\Database\Seeder;

/**
 * The section copy the home page shipped with.
 *
 * The how-it-works steps alternate their badge colour orange/green/orange/green
 * — that alternation is stored per step rather than derived from position,
 * matching the accent handling on home_stats, and it is what lets the about and
 * rider sections express their own non-alternating colours.
 *
 * Bodies are single-line here. MealHub's copies carried the source-wrapping of
 * the Blade files they were transcribed from — a real newline plus a dozen
 * spaces mid-sentence, which HTML collapsed on render. JSON does not collapse
 * anything, so shipping those verbatim would put visible whitespace inside a
 * sentence for any client that honours newlines. The only newline kept is the
 * About badge's, which is meaningful (see below).
 */
class HomeSectionSeeder extends Seeder
{
    public function run(): void
    {
        $this->about();
        $this->howItWorks();
        $this->mealTypes();
        $this->featuredRestaurants();
        $this->delivery();
        $this->cta();
    }

    /**
     * `badge_text` carries a real newline and it is deliberate — the badge
     * renders on two lines. It is stored as plain text rather than markup so an
     * admin never types HTML; clients must honour the break rather than
     * collapsing it.
     */
    private function about(): void
    {
        $about = HomeSection::firstOrCreate(
            ['key' => HomeSection::KEY_ABOUT],
            [
                'eyebrow' => 'Who we are',
                'heading' => 'A smarter way to eat well,',
                'heading_accent' => 'every single day',
                'body' => 'MealHub connects health-conscious customers, local restaurants and reliable delivery riders on one platform. Tell us your dietary goals and health profile — we generate a meal plan, you order in a tap, and fresh food arrives at your door.',
                'image_url' => 'https://images.unsplash.com/photo-1490645935967-10de6ba17061?auto=format&fit=crop&w=720&q=80',
                'extras' => [
                    'badge_icon' => 'bi bi-heart-pulse-fill',
                    'badge_text' => "Nutrition-first\nmeal planning",
                ],
                'is_published' => true,
            ],
        );

        $features = [
            ['title' => 'Personalised meal plans', 'body' => 'Plans built around your calories, allergies and health profile.', 'icon_class' => 'bi bi-clipboard2-pulse', 'accent' => 'green'],
            ['title' => 'Trusted local kitchens', 'body' => 'Verified restaurants with quality ratings and hygiene checks.', 'icon_class' => 'bi bi-shield-check', 'accent' => 'orange'],
            ['title' => 'Fast, tracked delivery', 'body' => 'Live order tracking and credits for a seamless checkout.', 'icon_class' => 'bi bi-lightning-charge', 'accent' => 'green'],
        ];

        foreach ($features as $index => $feature) {
            $about->features()->firstOrCreate(
                ['title' => $feature['title']],
                $feature + ['is_published' => true, 'sort_order' => $index + 1],
            );
        }
    }

    /**
     * Chrome only — the cards themselves are meal_categories rows, seeded by
     * MealCategorySeeder and queried independently of this table.
     */
    private function mealTypes(): void
    {
        HomeSection::firstOrCreate(
            ['key' => HomeSection::KEY_MEAL_TYPES],
            [
                'eyebrow' => 'Explore',
                'heading' => 'Meals for every goal',
                'body' => 'Pick a category that matches how you want to eat today.',
                'is_published' => true,
            ],
        );
    }

    /**
     * Chrome only — the cards are featured_restaurants rows, seeded by
     * FeaturedRestaurantSeeder and queried independently of this table.
     *
     * The "View all" button has no destination yet; there is no all-restaurants
     * page to point it at, so it stays '#' for an admin to fill in.
     */
    private function featuredRestaurants(): void
    {
        HomeSection::firstOrCreate(
            ['key' => HomeSection::KEY_FEATURED_RESTAURANTS],
            [
                'eyebrow' => 'Our partners',
                'heading' => 'Featured restaurants',
                'body' => 'Hand-picked kitchens our customers love.',
                'extras' => ['cta_label' => 'View all', 'cta_url' => '#'],
                'is_published' => true,
            ],
        );
    }

    /**
     * The rider panel's perks all render orange, so their accent is seeded to
     * match rather than left at the default.
     */
    private function delivery(): void
    {
        $delivery = HomeSection::firstOrCreate(
            ['key' => HomeSection::KEY_DELIVERY],
            [
                'eyebrow' => 'Earn with us',
                'heading' => 'Become a MealHub delivery partner',
                'body' => 'Ride on your own schedule, get paid weekly and grow with a platform that treats its riders right. Flexible hours, fair pay, real support.',
                'image_url' => 'https://images.unsplash.com/photo-1599058917212-d750089bc07e?auto=format&fit=crop&w=620&q=80',
                'extras' => [
                    'cta_label' => 'Start riding today',
                    'cta_icon' => 'bi bi-bicycle',
                    'cta_url' => '#',
                ],
                'is_published' => true,
            ],
        );

        $perks = [
            ['title' => 'Competitive weekly earnings', 'body' => 'Transparent payouts + tips, paid straight to your account.', 'icon_class' => 'bi bi-cash-coin'],
            ['title' => 'Total flexibility', 'body' => 'Go online whenever you want — full-time or a few hours.', 'icon_class' => 'bi bi-clock-history'],
            ['title' => 'Easy rider app', 'body' => 'Smart routing, live order details and instant support.', 'icon_class' => 'bi bi-phone'],
        ];

        foreach ($perks as $index => $perk) {
            $delivery->features()->firstOrCreate(
                ['title' => $perk['title']],
                $perk + ['accent' => 'orange', 'is_published' => true, 'sort_order' => $index + 1],
            );
        }
    }

    private function howItWorks(): void
    {
        $howItWorks = HomeSection::firstOrCreate(
            ['key' => HomeSection::KEY_HOW_IT_WORKS],
            [
                'eyebrow' => 'Simple steps',
                'heading' => 'How MealHub works',
                'body' => 'From your health goal to your doorstep in four easy steps.',
                'is_published' => true,
            ],
        );

        $steps = [
            ['title' => 'Set your profile', 'body' => 'Add your health profile, diet preferences and calorie goals.', 'accent' => 'orange'],
            ['title' => 'Get a meal plan', 'body' => 'We suggest balanced meals from restaurants near you.', 'accent' => 'green'],
            ['title' => 'Order & pay', 'body' => 'Checkout with card, cash or wallet credits in seconds.', 'accent' => 'orange'],
            ['title' => 'Enjoy delivery', 'body' => 'A rider brings it fresh while you track in real time.', 'accent' => 'green'],
        ];

        foreach ($steps as $index => $step) {
            $howItWorks->features()->firstOrCreate(
                ['title' => $step['title']],
                $step + ['is_published' => true, 'sort_order' => $index + 1],
            );
        }
    }

    /**
     * The two store links are fixed cardinality — there is no third app store —
     * so they are extras rather than repeatable feature rows. Both are '#'
     * today; no real listing exists yet.
     */
    private function cta(): void
    {
        HomeSection::firstOrCreate(
            ['key' => HomeSection::KEY_CTA],
            [
                'heading' => 'Ready to eat well, the easy way?',
                'body' => 'Join thousands enjoying fresh, goal-based meals delivered daily. Download the app or sign up in seconds.',
                'extras' => ['app_store_url' => '#', 'google_play_url' => '#'],
                'is_published' => true,
            ],
        );
    }
}
