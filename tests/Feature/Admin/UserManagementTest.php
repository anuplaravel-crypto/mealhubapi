<?php

namespace Tests\Feature\Admin;

use App\Events\UserStatusChanged;
use App\Models\City;
use App\Models\RiderVehicle;
use App\Models\User;
use App\Notifications\AccountStatusNotification;
use App\Services\Media\MediaPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The admin's surface over everybody else's account — three lists, three
 * profile reads, three activation toggles, and the rider vehicle photo.
 *
 * Four rules these tests exist to pin, each of which is a security property
 * rather than a behaviour:
 *
 * - **The role is a scope, not a filter.** `admin/customers` must never answer
 *   with a rider, and an id naming one must 404 rather than resolve. This is
 *   what stands in for a Policy on those routes, so it is tested as hard as a
 *   Policy would be.
 * - **An admin is not manageable through these routes.** The four roles share
 *   one table, and nothing else stops an admin deactivating a colleague — or
 *   themselves — except the lookup refusing to find them.
 * - **The toggle's consequences hang off the event.** The mail and the rider's
 *   vehicle both follow from `UserStatusChanged`, so both are asserted from the
 *   endpoint rather than from the listeners in isolation.
 * - **No private filename leaves the server.** The admin view drops the profile
 *   picture entirely and reports only whether one exists.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();
    }

    /**
     * A signed-in user with a real token — `Sanctum::actingAs()` yields a
     * `TransientToken` with no primary key, which the rest of the suite avoids
     * for the same reason.
     *
     * Every test signs in exactly one user: the sanctum guard memoizes the
     * resolved user for the lifetime of a test method, so a second
     * authenticated request as somebody else would answer as the first.
     *
     * @return array{0: User, 1: string}
     */
    private function signedIn(string $role = 'admin'): array
    {
        $user = User::factory()->{$role}()->create();

        return [$user, $user->createToken('auth-token')->plainTextToken];
    }

    /**
     * The three roles that must be turned away from every endpoint here — one
     * case each rather than a loop, for the memoization reason above.
     *
     * @return array<string, array{0: string}>
     */
    public static function nonAdminRoleProvider(): array
    {
        return [
            'customer' => ['customer'],
            'restaurant' => ['restaurant'],
            'rider' => ['rider'],
        ];
    }

    /**
     * The three managed roles and the path segment each is listed under.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function managedRoleProvider(): array
    {
        return [
            'customers' => ['customer', 'customers'],
            'restaurants' => ['restaurant', 'restaurants'],
            'riders' => ['rider', 'riders'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Lists
    |--------------------------------------------------------------------------
    */

    #[DataProvider('managedRoleProvider')]
    public function test_an_admin_reads_each_role_list(string $role, string $segment): void
    {
        [, $token] = $this->signedIn();
        User::factory()->{$role}()->count(3)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/admin/{$segment}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonStructure([
                'data' => [['id', 'firstName', 'lastName', 'email', 'role', 'status', 'is_email_verified', 'has_profile_picture']],
                'meta' => ['current_page', 'per_page', 'last_page', 'total', 'from', 'to', 'links'],
            ]);
    }

    #[DataProvider('managedRoleProvider')]
    public function test_a_list_holds_only_its_own_role(string $role, string $segment): void
    {
        [$admin, $token] = $this->signedIn();

        // One account of every role, so a listing that failed to scope would
        // show the other three — including the signed-in admin.
        foreach (['customer', 'restaurant', 'rider'] as $other) {
            User::factory()->{$other}()->create();
        }

        $rows = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/admin/{$segment}")
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame($role, $rows[0]['role']);
        $this->assertNotSame($admin->id, $rows[0]['id']);
    }

    public function test_the_list_never_holds_an_admin(): void
    {
        [, $token] = $this->signedIn();
        User::factory()->admin()->count(2)->create();
        User::factory()->customer()->create();

        foreach (['customers', 'restaurants', 'riders'] as $segment) {
            $rows = $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson("/api/v1/admin/{$segment}")
                ->assertStatus(200)
                ->json('data');

            $this->assertSame([], array_values(array_filter($rows, fn (array $row): bool => $row['role'] === 'admin')));
        }
    }

    public function test_the_list_can_be_searched_across_name_email_and_mobile(): void
    {
        [, $token] = $this->signedIn();
        User::factory()->customer()->create(['firstName' => 'Marguerite', 'email' => 'mg@example.com', 'mobile' => '01900000001']);
        User::factory()->customer()->create(['firstName' => 'Bartholomew', 'email' => 'bart@example.com', 'mobile' => '01900000002']);

        $request = $this->withHeader('Authorization', "Bearer {$token}");

        $this->assertSame(['mg@example.com'], array_column($request->getJson('/api/v1/admin/customers?search=Marguerite')->json('data'), 'email'));
        $this->assertSame(['bart@example.com'], array_column($request->getJson('/api/v1/admin/customers?search=bart@')->json('data'), 'email'));
        $this->assertSame(['mg@example.com'], array_column($request->getJson('/api/v1/admin/customers?search=0000001')->json('data'), 'email'));
    }

    public function test_a_search_term_is_not_a_wildcard_pattern(): void
    {
        [, $token] = $this->signedIn();
        User::factory()->customer()->count(3)->create();

        // Unescaped, `%` matches every row — a search box that quietly behaves
        // as a pattern language, and a way to dump the whole table.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/customers?search=%')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        [, $token] = $this->signedIn();
        User::factory()->customer()->count(2)->create();
        User::factory()->customer()->inactive()->create(['email' => 'pending@example.com']);

        $request = $this->withHeader('Authorization', "Bearer {$token}");

        $request->getJson('/api/v1/admin/customers?status=1')->assertStatus(200)->assertJsonCount(2, 'data');

        $blocked = $request->getJson('/api/v1/admin/customers?status=0')->assertStatus(200)->json('data');
        $this->assertSame(['pending@example.com'], array_column($blocked, 'email'));

        // Absent means both, rather than defaulting to one of them.
        $request->getJson('/api/v1/admin/customers')->assertStatus(200)->assertJsonCount(3, 'data');
    }

    public function test_the_list_can_be_sorted_and_paged(): void
    {
        [, $token] = $this->signedIn();
        User::factory()->customer()->create(['firstName' => 'Zara']);
        User::factory()->customer()->create(['firstName' => 'Anders']);
        User::factory()->customer()->create(['firstName' => 'Mira']);

        $request = $this->withHeader('Authorization', "Bearer {$token}");

        $ascending = $request->getJson('/api/v1/admin/customers?sort=firstName&direction=asc')->assertStatus(200)->json('data');
        $this->assertSame(['Anders', 'Mira', 'Zara'], array_column($ascending, 'firstName'));

        $paged = $request->getJson('/api/v1/admin/customers?sort=firstName&direction=desc&per_page=2')->assertStatus(200);
        $paged->assertJsonCount(2, 'data')->assertJsonPath('meta.last_page', 2)->assertJsonPath('meta.per_page', 2);
        $this->assertSame(['Zara', 'Mira'], array_column($paged->json('data'), 'firstName'));
    }

    public function test_the_list_rejects_parameters_it_cannot_safely_use(): void
    {
        [, $token] = $this->signedIn();
        $request = $this->withHeader('Authorization', "Bearer {$token}");

        // `sort` reaches orderBy() as a column name — the whitelist is the only
        // thing between a query string and an identifier in SQL.
        $request->getJson('/api/v1/admin/customers?sort=password')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['sort']]);

        $request->getJson('/api/v1/admin/customers?direction=sideways')->assertStatus(422)->assertJsonStructure(['errors' => ['direction']]);
        $request->getJson('/api/v1/admin/customers?per_page=500')->assertStatus(422)->assertJsonStructure(['errors' => ['per_page']]);
        $request->getJson('/api/v1/admin/customers?per_page=0')->assertStatus(422)->assertJsonStructure(['errors' => ['per_page']]);
    }

    public function test_the_rider_list_costs_the_same_number_of_queries_at_any_size(): void
    {
        [, $token] = $this->signedIn();

        // Riders are the heaviest row: three location relations plus a vehicle.
        // The locations are populated on purpose — Laravel skips an eager-load
        // query whose foreign keys are all null, so a list of location-less
        // users would prove nothing about the relations it never loaded.
        $city = City::factory()->create();
        $rider = fn (): User => User::factory()->rider()->create([
            'country_id' => $city->county->country_id,
            'county_id' => $city->county_id,
            'city_id' => $city->id,
        ]);

        RiderVehicle::factory()->create(['rider_id' => $rider()]);

        $request = $this->withHeader('Authorization', "Bearer {$token}");

        // Warm-up request first. The sanctum guard memoizes the resolved user
        // for the lifetime of a test method, so the *first* authenticated
        // request in a method carries token-lookup queries the rest do not —
        // and comparing a cold request against a warm one would report a
        // fixed-cost difference as an N+1 that had been fixed.
        $request->getJson('/api/v1/admin/riders')->assertStatus(200);

        DB::enableQueryLog();
        $request->getJson('/api/v1/admin/riders')->assertStatus(200);
        $withOne = count(DB::getQueryLog());

        foreach (range(1, 6) as $ignored) {
            RiderVehicle::factory()->create(['rider_id' => $rider()]);
        }

        // Flushed *after* the extra rows are created, so the factories' own
        // inserts are not counted as the endpoint's work.
        DB::flushQueryLog();

        $request->getJson('/api/v1/admin/riders')->assertStatus(200)->assertJsonCount(7, 'data');
        $withMany = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertGreaterThanOrEqual(4, $withOne, 'The list stopped eager-loading the relations this test exists to measure.');
        $this->assertSame($withOne, $withMany, 'The rider list grew a query per row — an N+1 on the vehicle or location relations.');
    }

    public function test_an_anonymous_caller_cannot_read_a_list(): void
    {
        $this->getJson('/api/v1/admin/customers')->assertStatus(401);
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function test_only_an_admin_may_read_a_list(string $role): void
    {
        [, $token] = $this->signedIn($role);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/customers')
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Profile reads
    |--------------------------------------------------------------------------
    */

    public function test_an_admin_reads_a_customer_profile(): void
    {
        [, $token] = $this->signedIn();
        $customer = User::factory()->customer()->create(['image' => 'avatar.jpg']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/admin/customers/{$customer->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.email', $customer->email)
            ->assertJsonPath('data.role', 'customer')
            ->assertJsonPath('data.has_profile_picture', true)
            // A customer files no paperwork and rides no vehicle, so neither
            // key is structurally present rather than present-and-null.
            ->assertJsonMissingPath('data.documents')
            ->assertJsonMissingPath('data.vehicle');
    }

    public function test_a_profile_never_names_the_stored_picture(): void
    {
        [, $token] = $this->signedIn();
        $customer = User::factory()->customer()->create(['image' => 'private-name.jpg']);

        $body = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/admin/customers/{$customer->id}")
            ->assertStatus(200)
            ->assertJsonMissingPath('data.image')
            ->assertJsonMissingPath('data.image_url')
            ->getContent();

        $this->assertStringNotContainsString('private-name.jpg', (string) $body);
    }

    public function test_a_rider_profile_carries_the_vehicle_and_its_admin_photo_address(): void
    {
        [, $token] = $this->signedIn();
        $rider = User::factory()->rider()->create();
        $vehicle = RiderVehicle::factory()->create(['rider_id' => $rider->id, 'image' => 'plate.jpg']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/admin/riders/{$rider->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.vehicle.id', $vehicle->id)
            ->assertJsonPath('data.vehicle.registration_number', $vehicle->registration_number)
            ->assertJsonPath('data.vehicle.image_url', route('api.v1.admin.riders.vehicle.image', ['rider' => $rider->id]));
    }

    public function test_a_rider_without_a_vehicle_reports_a_null_one(): void
    {
        [, $token] = $this->signedIn();
        $rider = User::factory()->rider()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/admin/riders/{$rider->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.vehicle', null);
    }

    public function test_a_restaurant_profile_carries_its_documents_and_their_admin_addresses(): void
    {
        [, $token] = $this->signedIn();
        $restaurant = User::factory()->restaurant()->create(['doc_image1' => 'licence.jpg', 'doc_image2' => null]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/admin/restaurants/{$restaurant->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.documents.is_complete', false)
            ->assertJsonPath('data.documents.documents.0.on_file', true)
            ->assertJsonPath('data.documents.documents.0.url', route('api.v1.admin.restaurants.documents.show', ['restaurant' => $restaurant->id, 'slot' => 1]))
            ->assertJsonPath('data.documents.documents.1.on_file', false)
            ->assertJsonPath('data.documents.documents.1.url', null);
    }

    #[DataProvider('managedRoleProvider')]
    public function test_an_id_from_another_role_is_not_found(string $role, string $segment): void
    {
        [, $token] = $this->signedIn();

        // A row of every *other* role, each of which the scoped lookup must
        // refuse to resolve under this segment.
        $others = collect(['customer', 'restaurant', 'rider', 'admin'])
            ->reject(fn (string $other): bool => $other === $role)
            ->map(fn (string $other): User => User::factory()->{$other}()->create());

        $request = $this->withHeader('Authorization', "Bearer {$token}");

        foreach ($others as $other) {
            $request->getJson("/api/v1/admin/{$segment}/{$other->id}")
                ->assertStatus(404)
                ->assertJsonPath('success', false);

            $request->patchJson("/api/v1/admin/{$segment}/{$other->id}/toggle-status")->assertStatus(404);
        }
    }

    public function test_an_unknown_id_is_not_found(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/customers/999999')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function test_only_an_admin_may_read_a_profile(string $role): void
    {
        [, $token] = $this->signedIn($role);
        $customer = User::factory()->customer()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/admin/customers/{$customer->id}")
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | The activation toggle
    |--------------------------------------------------------------------------
    */

    public function test_toggling_deactivates_an_active_account(): void
    {
        [, $token] = $this->signedIn();
        $customer = User::factory()->customer()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/admin/customers/{$customer->id}/toggle-status")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', false)
            ->assertJsonPath('message', 'Customer account deactivated.');

        $this->assertFalse($customer->fresh()->status);
    }

    public function test_toggling_activates_a_pending_account(): void
    {
        [, $token] = $this->signedIn();
        $restaurant = User::factory()->restaurant()->inactive()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/toggle-status")
            ->assertStatus(200)
            ->assertJsonPath('data.status', true)
            ->assertJsonPath('message', 'Restaurant account activated.');

        $this->assertTrue($restaurant->fresh()->status);
    }

    public function test_toggling_fires_the_status_event(): void
    {
        Event::fake([UserStatusChanged::class]);

        [, $token] = $this->signedIn();
        $customer = User::factory()->customer()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/admin/customers/{$customer->id}/toggle-status")
            ->assertStatus(200);

        Event::assertDispatched(
            UserStatusChanged::class,
            fn (UserStatusChanged $event): bool => $event->user->is($customer) && $event->activated === false,
        );
    }

    public function test_toggling_tells_the_account_holder(): void
    {
        [, $token] = $this->signedIn();
        $rider = User::factory()->rider()->inactive()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/admin/riders/{$rider->id}/toggle-status")
            ->assertStatus(200);

        Notification::assertSentTo(
            $rider,
            AccountStatusNotification::class,
            fn (AccountStatusNotification $notification, array $channels): bool => $channels === ['mail', 'database'],
        );
    }

    public function test_deactivating_a_rider_deactivates_their_vehicle(): void
    {
        [, $token] = $this->signedIn();
        $rider = User::factory()->rider()->create();
        $vehicle = RiderVehicle::factory()->create(['rider_id' => $rider->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/admin/riders/{$rider->id}/toggle-status")
            ->assertStatus(200)
            // Read back from the response, not just the database: the service
            // re-reads after the listeners run precisely so the payload cannot
            // report the pre-toggle vehicle.
            ->assertJsonPath('data.status', false)
            ->assertJsonPath('data.vehicle.is_active', false);

        $this->assertFalse($vehicle->fresh()->is_active);
    }

    public function test_activating_a_rider_activates_their_vehicle(): void
    {
        [, $token] = $this->signedIn();
        $rider = User::factory()->rider()->inactive()->create();
        $vehicle = RiderVehicle::factory()->inactive()->create(['rider_id' => $rider->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/admin/riders/{$rider->id}/toggle-status")
            ->assertStatus(200)
            ->assertJsonPath('data.vehicle.is_active', true);

        $this->assertTrue($vehicle->fresh()->is_active);
    }

    public function test_toggling_a_rider_leaves_other_riders_vehicles_alone(): void
    {
        [, $token] = $this->signedIn();
        $rider = User::factory()->rider()->create();
        RiderVehicle::factory()->create(['rider_id' => $rider->id]);

        $bystander = RiderVehicle::factory()->create(['rider_id' => User::factory()->rider()]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/admin/riders/{$rider->id}/toggle-status")
            ->assertStatus(200);

        $this->assertTrue($bystander->fresh()->is_active);
    }

    public function test_toggling_a_rider_without_a_vehicle_is_not_an_error(): void
    {
        [, $token] = $this->signedIn();
        $rider = User::factory()->rider()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/admin/riders/{$rider->id}/toggle-status")
            ->assertStatus(200)
            ->assertJsonPath('data.vehicle', null);
    }

    public function test_an_anonymous_caller_cannot_toggle(): void
    {
        $customer = User::factory()->customer()->create();

        $this->patchJson("/api/v1/admin/customers/{$customer->id}/toggle-status")->assertStatus(401);

        $this->assertTrue($customer->fresh()->status);
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function test_only_an_admin_may_toggle(string $role): void
    {
        [, $token] = $this->signedIn($role);
        $customer = User::factory()->customer()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/admin/customers/{$customer->id}/toggle-status")
            ->assertStatus(403);

        $this->assertTrue($customer->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | The rider vehicle photo
    |--------------------------------------------------------------------------
    */

    /**
     * Put a vehicle photo on the private disk without going through the rider's
     * own upload endpoint — a second authenticated request in one test method
     * would resolve as the first user.
     */
    private function storeVehiclePhoto(User $rider, string $filename = 'plate.jpg'): RiderVehicle
    {
        foreach (MediaPlacement::Personal->variants() as $variant) {
            Storage::disk(MediaPlacement::Personal->disk())->put(
                MediaPlacement::Personal->path('rider/'.RiderVehicle::IMAGE_COLLECTION, $variant, $filename),
                "photo-{$variant}",
            );
        }

        return RiderVehicle::factory()->create(['rider_id' => $rider->id, 'image' => $filename]);
    }

    public function test_an_admin_streams_a_named_riders_vehicle_photo(): void
    {
        [, $token] = $this->signedIn();
        $rider = User::factory()->rider()->create();
        $this->storeVehiclePhoto($rider);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/admin/riders/{$rider->id}/vehicle/image");

        $response->assertStatus(200);
        // Defaults to `large` rather than `medium`: an admin is reading a plate,
        // not glancing at a thumbnail.
        $this->assertSame('photo-large', $response->streamedContent());
    }

    public function test_the_streamed_variant_is_the_one_asked_for(): void
    {
        [, $token] = $this->signedIn();
        $rider = User::factory()->rider()->create();
        $this->storeVehiclePhoto($rider);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/admin/riders/{$rider->id}/vehicle/image?variant=small");

        $response->assertStatus(200);
        $this->assertSame('photo-small', $response->streamedContent());
    }

    public function test_an_id_that_does_not_name_a_rider_is_refused_by_the_policy(): void
    {
        [, $token] = $this->signedIn();

        // `role:admin` proves the caller, not the target. Only the Policy stops
        // an id naming a customer, a restaurant, or another admin from reaching
        // a route that streams a private file.
        $notARider = User::factory()->restaurant()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/admin/riders/{$notARider->id}/vehicle/image")
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_a_rider_with_no_photo_is_not_found(): void
    {
        [, $token] = $this->signedIn();
        $rider = User::factory()->rider()->create();
        RiderVehicle::factory()->create(['rider_id' => $rider->id, 'image' => null]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/admin/riders/{$rider->id}/vehicle/image")
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_an_anonymous_caller_cannot_stream_a_vehicle_photo(): void
    {
        $rider = User::factory()->rider()->create();
        $this->storeVehiclePhoto($rider);

        $this->getJson("/api/v1/admin/riders/{$rider->id}/vehicle/image")->assertStatus(401);
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function test_only_an_admin_may_stream_a_named_riders_vehicle_photo(string $role): void
    {
        [, $token] = $this->signedIn($role);
        $rider = User::factory()->rider()->create();
        $this->storeVehiclePhoto($rider);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/admin/riders/{$rider->id}/vehicle/image")
            ->assertStatus(403);
    }
}
