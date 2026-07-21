<?php

namespace Tests\Feature\Database;

use App\Models\FeaturedRestaurant;
use App\Models\HomeSection;
use App\Models\HomeStat;
use App\Models\NavMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the reference/CMS seed data down.
 *
 * These counts are not arbitrary — they are the content the public home page
 * renders, carried over from MealHub so the two projects show the same site.
 * A drop here means a section silently lost rows.
 */
class SeederIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Expected row count per table after a full seed.
     *
     * @var array<string, int>
     */
    private const EXPECTED_COUNTS = [
        'countries' => 3,
        'counties' => 8,
        'cities' => 24,
        'site_settings' => 1,
        'home_stats' => 7,
        'nav_menus' => 23,
        'home_sections' => 6,
        'section_features' => 10,
        'meal_categories' => 4,
        'featured_restaurants' => 6,
        'testimonials' => 3,
        'users' => 4,
    ];

    public function test_seeding_produces_the_expected_row_counts(): void
    {
        $this->seed();

        foreach (self::EXPECTED_COUNTS as $table => $expected) {
            $this->assertDatabaseCount($table, $expected);
        }
    }

    public function test_transactional_tables_are_not_seeded(): void
    {
        $this->seed();

        // Vehicles and newsletter signups are user-submitted data, not
        // reference content — a seeder creating them would be inventing
        // records that no one submitted.
        $this->assertDatabaseCount('rider_vehicles', 0);
        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        foreach (self::EXPECTED_COUNTS as $table => $expected) {
            $this->assertDatabaseCount($table, $expected);
        }
    }

    public function test_every_home_section_key_is_seeded(): void
    {
        $this->seed();

        $keys = HomeSection::pluck('key')->all();

        foreach (HomeSection::KEYS as $key) {
            $this->assertContains($key, $keys, "Home section [{$key}] was not seeded.");
        }
    }

    /**
     * The three SPA-targeted links: "Sign in", "Get started" and "Add your
     * restaurant". Everything else is an anchor or a placeholder URL, and a
     * link must never carry both a route key and a URL.
     */
    public function test_only_the_three_spa_links_carry_a_route_key(): void
    {
        $this->seed();

        $routed = NavMenu::whereNotNull('route_key')->get();

        $this->assertCount(3, $routed);
        $this->assertEqualsCanonicalizing(
            ['login', 'register', 'restaurant.register'],
            $routed->pluck('route_key')->all(),
        );

        foreach ($routed as $link) {
            $this->assertNull($link->url, "Link [{$link->label}] has both a route key and a URL.");
        }
    }

    /**
     * A stat-bar value feeds a client-side counter animation, so it must parse
     * as a number. Hero values are printed verbatim and carry their own suffix
     * ("15k+"), which is exactly what would animate to NaN in the other slot.
     */
    public function test_stat_bar_values_are_digits_only(): void
    {
        $this->seed();

        $statBar = HomeStat::where('placement', HomeStat::PLACEMENT_STAT_BAR)->get();

        $this->assertNotEmpty($statBar);

        foreach ($statBar as $stat) {
            $this->assertMatchesRegularExpression('/^\d+$/', $stat->value, "Stat bar value [{$stat->value}] is not digits only.");
        }
    }

    /**
     * Every seeded card is a placeholder for a restaurant that does not exist
     * as a domain entity yet — see the table's migration.
     */
    public function test_no_featured_restaurant_is_linked_to_an_account(): void
    {
        $this->seed();

        $this->assertSame(0, FeaturedRestaurant::whereNotNull('user_id')->count());
    }

    /**
     * The About badge renders on two lines, and the break is stored as a real
     * newline rather than markup. Collapsing it would flatten the badge.
     */
    public function test_the_about_badge_keeps_its_line_break(): void
    {
        $this->seed();

        $about = HomeSection::where('key', HomeSection::KEY_ABOUT)->sole();

        $this->assertStringContainsString("\n", $about->extra('badge_text'));
    }

    /**
     * Section bodies were transcribed from Blade files whose source wrapping
     * put a newline plus indentation mid-sentence. HTML collapsed that; JSON
     * will not, so it has to be gone from the stored copy.
     */
    public function test_section_bodies_carry_no_source_wrapping(): void
    {
        $this->seed();

        foreach (HomeSection::all() as $section) {
            $this->assertStringNotContainsString("\n", (string) $section->body, "Section [{$section->key}] body contains a newline.");
            $this->assertStringNotContainsString('  ', (string) $section->body, "Section [{$section->key}] body contains repeated spaces.");
        }
    }

    public function test_dev_users_cover_all_four_roles(): void
    {
        $this->seed();

        foreach (['admin', 'customer', 'restaurant', 'rider'] as $role) {
            $this->assertDatabaseHas('users', [
                'email' => "{$role}@mealhub.test",
                'role' => $role,
                'status' => true,
                'is_email_verified' => true,
            ]);
        }
    }
}
