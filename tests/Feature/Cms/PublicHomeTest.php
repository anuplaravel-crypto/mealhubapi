<?php

namespace Tests\Feature\Cms;

use App\Models\FeaturedRestaurant;
use App\Models\HomeSection;
use App\Models\HomeStat;
use App\Models\MealCategory;
use App\Models\NavMenu;
use App\Models\SectionFeature;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The public home payload. Anonymous, read-only published content, so the only
 * failure paths worth pinning are structural: an unseeded database must still
 * answer, unpublished rows must never appear, and every group the client
 * iterates must be present even when empty.
 *
 * Ports MealHub's HomePageCmsTest. Its assertions were markup-level — the point
 * there was proving the CMS cutover was a visual no-op — so what carries over
 * is the rules behind them (publish filtering, sort order, image fallbacks,
 * derived step numbering), asserted against JSON instead of HTML.
 */
class PublicHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_every_group_the_client_renders(): void
    {
        $this->seed();

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'site' => ['site_name', 'brand_primary_text', 'brand_accent_text', 'meta_title', 'meta_description', 'logo_url', 'footer_blurb'],
                    'nav_menus' => ['navbar', 'footer_menu', 'social', 'legal'],
                    'hero_stats' => [['id', 'label', 'value', 'icon_class', 'accent', 'sort_order']],
                    'stat_bar_stats' => [['id', 'label', 'value', 'icon_class', 'accent', 'sort_order']],
                    'sections' => [HomeSection::KEY_ABOUT => ['id', 'key', 'eyebrow', 'heading', 'heading_accent', 'body', 'image_url', 'extras', 'features']],
                    'meal_categories' => [['id', 'name', 'tagline', 'image_url', 'sort_order']],
                    'featured_restaurants' => [['id', 'name', 'image_url', 'rating', 'location', 'cuisines', 'delivery_time', 'tag', 'perk_label', 'perk_variant', 'sort_order']],
                    'testimonials' => [['id', 'quote', 'author_name', 'author_role', 'avatar_url', 'rating', 'sort_order']],
                ],
            ]);
    }

    public function test_it_needs_no_authentication(): void
    {
        $this->getJson('/api/v1/home')->assertOk();
    }

    public function test_an_unseeded_database_still_answers_with_the_default_branding(): void
    {
        $this->assertDatabaseCount('site_settings', 0);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.site.site_name', 'MealHub')
            ->assertJsonPath('data.site.brand_primary_text', 'Meal')
            ->assertJsonPath('data.site.brand_accent_text', 'Hub')
            ->assertJsonPath('data.site.logo_url', null);

        // Reading the page must not write to it — unlike MealHub, whose
        // firstOrCreate seeded the row on first render.
        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_every_group_is_present_and_empty_on_an_unseeded_database(): void
    {
        $response = $this->getJson('/api/v1/home')->assertOk();

        // An absent key is `undefined` in JavaScript and throws on .map(), so
        // each list has to survive as an empty list rather than disappear.
        foreach (['hero_stats', 'stat_bar_stats', 'meal_categories', 'featured_restaurants', 'testimonials'] as $group) {
            $this->assertSame([], $response->json("data.{$group}"), "{$group} should be an empty list");
        }

        foreach (NavMenu::LOCATIONS as $location) {
            $this->assertSame([], $response->json("data.nav_menus.{$location}"), "nav_menus.{$location} should be an empty list");
        }

        // Sections are looked up by key rather than iterated, so an empty map
        // is enough — but it must still be an object, not a list.
        $this->assertSame([], $response->json('data.sections'));
        $this->assertStringContainsString('"sections":{}', $response->getContent());
    }

    public function test_the_site_logo_resolves_to_an_absolute_url(): void
    {
        $settings = SiteSetting::factory()->withLogo()->create(['id' => SiteSetting::SINGLETON_ID]);

        $logoUrl = $this->getJson('/api/v1/home')->assertOk()->json('data.site.logo_url');

        // Absolute, and pointing at the small variant of the site collection —
        // a root-relative path would resolve against the SPA's own host.
        $this->assertStringStartsWith('http', $logoUrl);
        $this->assertStringEndsWith("/cms/site/small/{$settings->logo}", $logoUrl);
    }

    public function test_it_groups_nav_links_by_location_and_orders_them(): void
    {
        NavMenu::factory()->navbar()->create(['label' => 'Second Link', 'sort_order' => 2]);
        NavMenu::factory()->navbar()->create(['label' => 'First Link', 'sort_order' => 1]);
        NavMenu::factory()->social()->create(['label' => 'Facebook']);
        NavMenu::factory()->footerMenu('Company')->create(['label' => 'About us']);

        $response = $this->getJson('/api/v1/home')->assertOk();

        $this->assertSame(['First Link', 'Second Link'], $response->json('data.nav_menus.navbar.*.label'));
        $this->assertSame(['Facebook'], $response->json('data.nav_menus.social.*.label'));
        $this->assertSame(['About us'], $response->json('data.nav_menus.footer_menu.*.label'));
        $this->assertSame([], $response->json('data.nav_menus.legal'));
    }

    public function test_it_hides_unpublished_nav_links(): void
    {
        NavMenu::factory()->navbar()->create(['label' => 'Visible Link']);
        NavMenu::factory()->navbar()->unpublished()->create(['label' => 'Hidden Link']);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonCount(1, 'data.nav_menus.navbar')
            ->assertJsonPath('data.nav_menus.navbar.0.label', 'Visible Link');
    }

    public function test_a_nav_link_ships_its_route_key_unresolved(): void
    {
        NavMenu::factory()->navbar()->withRouteKey('customer.login')->create(['label' => 'Sign in']);

        // route_key is an opaque token the SPA maps to its own path, not a
        // Laravel route name — resolving it here would 500 on a stale value.
        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.nav_menus.navbar.0.route_key', 'customer.login')
            ->assertJsonPath('data.nav_menus.navbar.0.url', null);
    }

    public function test_it_keeps_the_two_stat_placements_apart(): void
    {
        HomeStat::factory()->hero()->create(['label' => 'Hero Only']);
        HomeStat::factory()->statBar()->create(['label' => 'Bar Only']);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonCount(1, 'data.hero_stats')
            ->assertJsonPath('data.hero_stats.0.label', 'Hero Only')
            ->assertJsonCount(1, 'data.stat_bar_stats')
            ->assertJsonPath('data.stat_bar_stats.0.label', 'Bar Only');
    }

    public function test_it_hides_unpublished_stats_and_orders_the_rest(): void
    {
        HomeStat::factory()->statBar()->create(['label' => 'Second Stat', 'sort_order' => 2]);
        HomeStat::factory()->statBar()->create(['label' => 'First Stat', 'sort_order' => 1]);
        HomeStat::factory()->statBar()->unpublished()->create(['label' => 'Hidden Stat']);

        $labels = $this->getJson('/api/v1/home')->assertOk()->json('data.stat_bar_stats.*.label');

        $this->assertSame(['First Stat', 'Second Stat'], $labels);
    }

    public function test_it_keys_sections_by_their_key_and_nests_published_features(): void
    {
        $section = HomeSection::factory()->key(HomeSection::KEY_HOW_IT_WORKS)->create();
        SectionFeature::factory()->forSection($section)->create(['title' => 'Set your profile', 'sort_order' => 1]);
        SectionFeature::factory()->forSection($section)->create(['title' => 'Enjoy delivery', 'sort_order' => 2]);

        $response = $this->getJson('/api/v1/home')->assertOk();

        $this->assertSame(
            ['Set your profile', 'Enjoy delivery'],
            $response->json('data.sections.'.HomeSection::KEY_HOW_IT_WORKS.'.features.*.title'),
        );
    }

    public function test_hiding_a_step_renumbers_the_rest_by_removing_it_from_the_list(): void
    {
        $section = HomeSection::factory()->key(HomeSection::KEY_HOW_IT_WORKS)->create();
        SectionFeature::factory()->forSection($section)->create(['title' => 'Step One', 'sort_order' => 1]);
        SectionFeature::factory()->forSection($section)->unpublished()->create(['title' => 'Step Two', 'sort_order' => 2]);
        SectionFeature::factory()->forSection($section)->create(['title' => 'Step Three', 'sort_order' => 3]);

        $titles = $this->getJson('/api/v1/home')
            ->assertOk()
            ->json('data.sections.'.HomeSection::KEY_HOW_IT_WORKS.'.features.*.title');

        // The number is render position, never a stored column, so an unpublished
        // step leaves 1..2 rather than a gap at 2.
        $this->assertSame(['Step One', 'Step Three'], $titles);
    }

    public function test_hiding_a_section_removes_its_key_entirely(): void
    {
        HomeSection::factory()->key(HomeSection::KEY_ABOUT)->create();
        HomeSection::factory()->key(HomeSection::KEY_CTA)->unpublished()->create();

        $sections = $this->getJson('/api/v1/home')->assertOk()->json('data.sections');

        $this->assertArrayHasKey(HomeSection::KEY_ABOUT, $sections);
        $this->assertArrayNotHasKey(HomeSection::KEY_CTA, $sections);
    }

    public function test_a_section_saved_without_extras_still_ships_an_object(): void
    {
        HomeSection::factory()->key(HomeSection::KEY_CTA)->create(['extras' => null]);

        $response = $this->getJson('/api/v1/home')->assertOk();

        // `{}` rather than null, so a client can read a missing key without
        // guarding the container first.
        $this->assertSame([], $response->json('data.sections.'.HomeSection::KEY_CTA.'.extras'));
        $this->assertStringContainsString('"extras":{}', $response->getContent());
    }

    public function test_section_extras_ship_verbatim_including_a_meaningful_newline(): void
    {
        HomeSection::factory()
            ->key(HomeSection::KEY_ABOUT)
            ->withExtras(['badge_icon' => 'bi bi-heart-pulse-fill', 'badge_text' => "Nutrition-first\nmeal planning"])
            ->create();

        // The break in the About badge is content, not formatting — it has to
        // survive to the client rather than being collapsed or turned into a <br>.
        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.sections.'.HomeSection::KEY_ABOUT.'.extras.badge_text', "Nutrition-first\nmeal planning");
    }

    public function test_an_uploaded_section_image_wins_over_the_external_url(): void
    {
        HomeSection::factory()->key(HomeSection::KEY_ABOUT)->create([
            'image' => 'uploaded-section.jpg',
            'image_url' => 'https://example.com/external.jpg',
        ]);

        $imageUrl = $this->getJson('/api/v1/home')
            ->assertOk()
            ->json('data.sections.'.HomeSection::KEY_ABOUT.'.image_url');

        $this->assertStringEndsWith('/cms/sections/large/uploaded-section.jpg', $imageUrl);
    }

    public function test_a_section_with_neither_image_source_resolves_to_null(): void
    {
        HomeSection::factory()->key(HomeSection::KEY_ABOUT)->create(['image' => null, 'image_url' => null]);

        // Null means "render no image": an empty src resolves against the
        // client's own document and fetches the page as an image.
        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.sections.'.HomeSection::KEY_ABOUT.'.image_url', null);
    }

    public function test_it_hides_unpublished_meal_categories_and_orders_the_rest(): void
    {
        MealCategory::factory()->create(['name' => 'Second Category', 'sort_order' => 2]);
        MealCategory::factory()->create(['name' => 'First Category', 'sort_order' => 1]);
        MealCategory::factory()->unpublished()->create(['name' => 'Hidden Category']);

        $names = $this->getJson('/api/v1/home')->assertOk()->json('data.meal_categories.*.name');

        $this->assertSame(['First Category', 'Second Category'], $names);
    }

    public function test_meal_categories_are_returned_independently_of_their_section(): void
    {
        // The whole point of the split table: the cards have no foreign key to
        // home_sections, so they ship even when the envelope was never seeded.
        MealCategory::factory()->create(['name' => 'Orphan Category']);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonCount(0, 'data.sections')
            ->assertJsonCount(1, 'data.meal_categories')
            ->assertJsonPath('data.meal_categories.0.name', 'Orphan Category');
    }

    public function test_featured_restaurants_arrive_as_one_flat_ordered_list(): void
    {
        FeaturedRestaurant::factory()->count(6)->sequence(fn ($sequence) => [
            'name' => 'Kitchen '.($sequence->index + 1),
            'sort_order' => $sequence->index + 1,
        ])->create();

        $response = $this->getJson('/api/v1/home')->assertOk();

        // Not chunked into carousel slides the way MealHub's service returned
        // them — how many cards make a slide is the client's DOM decision.
        $response->assertJsonCount(6, 'data.featured_restaurants');
        $this->assertSame('Kitchen 1', $response->json('data.featured_restaurants.0.name'));
        $this->assertSame('Kitchen 6', $response->json('data.featured_restaurants.5.name'));
    }

    public function test_it_hides_unpublished_featured_restaurants(): void
    {
        FeaturedRestaurant::factory()->create(['name' => 'Visible Kitchen']);
        FeaturedRestaurant::factory()->unpublished()->create(['name' => 'Hidden Kitchen']);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonCount(1, 'data.featured_restaurants')
            ->assertJsonPath('data.featured_restaurants.0.name', 'Visible Kitchen');
    }

    public function test_a_featured_restaurant_never_exposes_the_linked_account_id(): void
    {
        FeaturedRestaurant::factory()->linkedTo()->create();

        $card = $this->getJson('/api/v1/home')->assertOk()->json('data.featured_restaurants.0');

        $this->assertArrayNotHasKey('user_id', $card);
    }

    public function test_optional_card_fields_ship_as_null_rather_than_being_dropped(): void
    {
        FeaturedRestaurant::factory()->create([
            'name' => 'Bare Kitchen',
            'tag' => null,
            'rating' => null,
            'perk_label' => null,
            'image' => null,
            'image_url' => null,
        ]);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.featured_restaurants.0.name', 'Bare Kitchen')
            ->assertJsonPath('data.featured_restaurants.0.tag', null)
            ->assertJsonPath('data.featured_restaurants.0.rating', null)
            ->assertJsonPath('data.featured_restaurants.0.perk_label', null)
            ->assertJsonPath('data.featured_restaurants.0.image_url', null);
    }

    public function test_it_hides_unpublished_testimonials_and_orders_the_rest(): void
    {
        Testimonial::factory()->create(['author_name' => 'Second Author', 'sort_order' => 2]);
        Testimonial::factory()->create(['author_name' => 'First Author', 'sort_order' => 1]);
        Testimonial::factory()->unpublished()->create(['author_name' => 'Hidden Author']);

        $authors = $this->getJson('/api/v1/home')->assertOk()->json('data.testimonials.*.author_name');

        $this->assertSame(['First Author', 'Second Author'], $authors);
    }

    public function test_an_uploaded_avatar_wins_over_the_external_url_and_resolves_small(): void
    {
        $testimonial = Testimonial::factory()->withUploadedAvatar()->create();

        $avatarUrl = $this->getJson('/api/v1/home')->assertOk()->json('data.testimonials.0.avatar_url');

        $this->assertStringEndsWith("/cms/testimonials/small/{$testimonial->avatar}", $avatarUrl);
    }

    public function test_a_testimonial_without_any_avatar_resolves_to_null(): void
    {
        Testimonial::factory()->withoutAvatar()->create();

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.testimonials.0.avatar_url', null);
    }

    public function test_a_testimonial_keeps_its_fractional_rating(): void
    {
        Testimonial::factory()->create(['rating' => 4.5]);

        // The client draws the star row from this, so the half has to survive
        // the decimal cast rather than rounding to 4 or 5.
        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.testimonials.0.rating', '4.5');
    }

    /**
     * The payload aggregates eight tables, so its query count is the number
     * that could quietly become one-per-row. It must stay flat: seven list
     * queries plus the section features' single eager load.
     */
    public function test_the_endpoint_does_not_scale_its_query_count_with_the_row_count(): void
    {
        $this->seed();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/v1/home')->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(8, $count);
    }
}
