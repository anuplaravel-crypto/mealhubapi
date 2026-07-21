<?php

namespace Tests\Feature\Database;

use App\Models\City;
use App\Models\Country;
use App\Models\County;
use App\Models\FeaturedRestaurant;
use App\Models\HomeSection;
use App\Models\HomeStat;
use App\Models\MealCategory;
use App\Models\NavMenu;
use App\Models\NewsletterSubscriber;
use App\Models\RiderVehicle;
use App\Models\SectionFeature;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Models\User;
use Database\Factories\DatabaseNotificationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises every factory and named state, so a later increment building
 * endpoints finds them all working.
 *
 * Caveat: this suite runs on SQLite (see phpunit.xml), which does not enforce
 * varchar lengths. A factory generating an over-long value passes here and
 * throws against MySQL — so run `migrate:fresh --seed` against MySQL too
 * rather than trusting this alone.
 */
class FactoryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_factory_and_role_states(): void
    {
        foreach (['admin', 'customer', 'restaurant', 'rider'] as $role) {
            $this->assertSame($role, User::factory()->{$role}()->create()->role);
        }

        $this->assertFalse(User::factory()->unverified()->create()->is_email_verified);
        $this->assertFalse(User::factory()->inactive()->create()->status);
        $this->assertTrue(User::factory()->withExpiredOtp()->create()->otp_expires_at->isPast());
    }

    /**
     * The column is varchar(20) and SQLite will not complain, so assert the
     * length directly — this is the exact bug the factory used to have.
     */
    public function test_user_factory_mobile_fits_the_column(): void
    {
        foreach (User::factory()->count(25)->create() as $user) {
            $this->assertLessThanOrEqual(20, strlen($user->mobile));
        }
    }

    /**
     * All three name columns are varchar(50) and SQLite will not complain, so
     * assert the lengths directly — same reasoning as the mobile column above.
     */
    public function test_location_factories_build_the_cascade(): void
    {
        $country = Country::factory()->withCascade(counties: 3, cities: 4)->create();

        $this->assertCount(3, $country->counties);
        $this->assertCount(4, $country->counties->first()->cities);

        $county = County::factory()->forCountry($country)->create();
        $this->assertSame($country->id, $county->country_id);
        $this->assertSame($county->id, City::factory()->forCounty($county)->create()->county_id);

        foreach ([Country::all(), County::all(), City::all()] as $rows) {
            foreach ($rows as $row) {
                $this->assertLessThanOrEqual(50, strlen($row->name));
            }
        }
    }

    public function test_rider_vehicle_factory_and_states(): void
    {
        $vehicle = RiderVehicle::factory()->create();

        $this->assertSame('rider', $vehicle->rider->role);
        $this->assertContains($vehicle->vehicle_type, RiderVehicle::VEHICLE_TYPES);

        $this->assertFalse(RiderVehicle::factory()->inactive()->create()->is_active);

        foreach (RiderVehicle::VEHICLE_TYPES as $type) {
            $this->assertSame($type, RiderVehicle::factory()->{$type}()->create()->vehicle_type);
        }
    }

    public function test_site_setting_factory_and_states(): void
    {
        $this->assertNull(SiteSetting::factory()->create()->logo);
        $this->assertNotNull(SiteSetting::factory()->withLogo()->create()->logo);
    }

    public function test_testimonial_factory_and_states(): void
    {
        $this->assertTrue(Testimonial::factory()->create()->is_published);
        $this->assertFalse(Testimonial::factory()->unpublished()->create()->is_published);

        // An upload always wins, so saving one must clear the external URL.
        $uploaded = Testimonial::factory()->withUploadedAvatar()->create();
        $this->assertNotNull($uploaded->avatar);
        $this->assertNull($uploaded->avatar_url);

        $none = Testimonial::factory()->withoutAvatar()->create();
        $this->assertNull($none->avatar);
        $this->assertNull($none->avatar_url);
    }

    public function test_home_stat_factory_and_placement_states(): void
    {
        $hero = HomeStat::factory()->hero()->create();
        $this->assertSame(HomeStat::PLACEMENT_HERO, $hero->placement);
        $this->assertNull($hero->icon_class);

        $statBar = HomeStat::factory()->statBar()->create();
        $this->assertSame(HomeStat::PLACEMENT_STAT_BAR, $statBar->placement);
        $this->assertMatchesRegularExpression('/^\d+$/', $statBar->value);

        $this->assertFalse(HomeStat::factory()->unpublished()->create()->is_published);
    }

    public function test_nav_menu_factory_and_states(): void
    {
        $this->assertSame(NavMenu::LOCATION_NAVBAR, NavMenu::factory()->navbar()->create()->location);
        $this->assertSame('Partners', NavMenu::factory()->footerMenu('Partners')->create()->group_label);
        $this->assertSame(NavMenu::LOCATION_SOCIAL, NavMenu::factory()->social()->create()->location);
        $this->assertSame(NavMenu::LOCATION_LEGAL, NavMenu::factory()->legal()->create()->location);
        $this->assertSame(NavMenu::VARIANT_OUTLINE, NavMenu::factory()->cta(NavMenu::VARIANT_OUTLINE)->create()->variant);
        $this->assertFalse(NavMenu::factory()->unpublished()->create()->is_published);

        // Route key and URL are mutually exclusive.
        $routed = NavMenu::factory()->withRouteKey('register')->create();
        $this->assertSame('register', $routed->route_key);
        $this->assertNull($routed->url);
    }

    /**
     * `key` is unique-indexed, so the factory default has to be generated —
     * a fixed default would collide on the second create().
     */
    public function test_home_section_factory_generates_unique_keys(): void
    {
        $sections = HomeSection::factory()->count(5)->create();

        $this->assertCount(5, $sections->pluck('key')->unique());

        $this->assertSame(HomeSection::KEY_ABOUT, HomeSection::factory()->key(HomeSection::KEY_ABOUT)->create()->key);
        $this->assertFalse(HomeSection::factory()->unpublished()->create()->is_published);

        $withExtras = HomeSection::factory()->withExtras(['badge_icon' => 'bi bi-heart'])->create();
        $this->assertSame('bi bi-heart', $withExtras->extra('badge_icon'));
        $this->assertSame('fallback', $withExtras->extra('missing', 'fallback'));
    }

    public function test_section_feature_factory_and_states(): void
    {
        $feature = SectionFeature::factory()->create();
        $this->assertInstanceOf(HomeSection::class, $feature->section);

        $section = HomeSection::factory()->create();
        $this->assertSame($section->id, SectionFeature::factory()->forSection($section)->create()->home_section_id);

        $this->assertSame('orange', SectionFeature::factory()->orange()->create()->accent);
        $this->assertFalse(SectionFeature::factory()->unpublished()->create()->is_published);
    }

    /**
     * `name` is unique-indexed — same reasoning as HomeSection's key.
     */
    public function test_meal_category_factory_generates_unique_names(): void
    {
        $this->assertCount(5, MealCategory::factory()->count(5)->create()->pluck('name')->unique());
        $this->assertFalse(MealCategory::factory()->unpublished()->create()->is_published);
    }

    public function test_featured_restaurant_factory_and_states(): void
    {
        $this->assertNull(FeaturedRestaurant::factory()->create()->user_id);

        $restaurant = User::factory()->restaurant()->create();
        $this->assertSame($restaurant->id, FeaturedRestaurant::factory()->linkedTo($restaurant)->create()->user_id);

        // Without an explicit user the state still has to produce a restaurant.
        $this->assertSame('restaurant', FeaturedRestaurant::factory()->linkedTo()->create()->restaurant->role);

        $this->assertSame('Top rated', FeaturedRestaurant::factory()->topRated()->create()->tag);
        $this->assertFalse(FeaturedRestaurant::factory()->unpublished()->create()->is_published);
    }

    /**
     * The only factory whose model is not one of ours — Laravel's
     * `DatabaseNotification` has no `HasFactory`, so it is instantiated
     * directly rather than through `Model::factory()`.
     */
    public function test_database_notification_factory_and_states(): void
    {
        $default = DatabaseNotificationFactory::new()->create();

        $this->assertNull($default->read_at);
        $this->assertInstanceOf(User::class, $default->notifiable);
        $this->assertSame('customer_registration', $default->data['type']);

        $user = User::factory()->rider()->create();
        $this->assertTrue($user->is(DatabaseNotificationFactory::new()->forUser($user)->create()->notifiable));

        $this->assertNotNull(DatabaseNotificationFactory::new()->read()->create()->read_at);
        $this->assertNull(DatabaseNotificationFactory::new()->read()->unread()->create()->read_at);

        $deactivated = DatabaseNotificationFactory::new()->accountStatus(activated: false)->create();
        $this->assertSame('account_status', $deactivated->data['type']);
        $this->assertFalse($deactivated->data['activated']);

        // The uuid primary key is the factory's to generate; nothing on the
        // model fills it in.
        $this->assertCount(2, DatabaseNotificationFactory::new()->count(2)->create()->pluck('id')->unique());
    }

    /**
     * The three states mirror the derived `status`, so a test never has to
     * hand-set the timestamp pair and risk an impossible combination.
     */
    public function test_newsletter_subscriber_factory_states_match_derived_status(): void
    {
        $pending = NewsletterSubscriber::factory()->create();
        $this->assertSame('pending', $pending->status);
        $this->assertFalse($pending->is_mailable);

        $confirmed = NewsletterSubscriber::factory()->confirmed()->create();
        $this->assertSame('confirmed', $confirmed->status);
        $this->assertTrue($confirmed->is_mailable);

        $this->assertTrue(NewsletterSubscriber::factory()->mailable()->create()->is_mailable);

        $unsubscribed = NewsletterSubscriber::factory()->unsubscribed()->create();
        $this->assertSame('unsubscribed', $unsubscribed->status);
        $this->assertFalse($unsubscribed->is_mailable);
    }
}
