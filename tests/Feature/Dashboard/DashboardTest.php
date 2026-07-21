<?php

namespace Tests\Feature\Dashboard;

use App\Models\RiderVehicle;
use App\Models\User;
use Database\Factories\DatabaseNotificationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The one dashboard route, and the four payloads it answers with.
 *
 * Four properties these tests exist to pin:
 *
 * - **The role decides the section, and the token decides the role.** Nothing a
 *   client sends selects a payload, so there is no request that makes a
 *   customer's dashboard answer with the admin's account counts.
 * - **The dashboard is not a second profile.** Address and the location rows
 *   must stay absent: the moment one appears here, it has two endpoints keeping
 *   it in step. Asserted as an absence, because that is the only way a field
 *   that drifts back in gets caught.
 * - **No private filename leaves the server.** The identity block carries the
 *   streaming endpoint's address and never the stored `image` name — the same
 *   rule `AdminUserResource` is held to.
 * - **The cost is fixed.** Neither the admin's counts nor the rider's vehicle
 *   may grow a query per row; a dashboard is the classic N+1 offender, so it is
 *   measured rather than reviewed.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

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
    private function signedInUser(string $role = 'customer'): array
    {
        $user = User::factory()->{$role}()->create();

        return [$user, $user->createToken('auth-token')->plainTextToken];
    }

    private function notifications(): DatabaseNotificationFactory
    {
        return DatabaseNotificationFactory::new();
    }

    /**
     * The four roles, one case each — a data provider rather than a loop, for
     * the memoization reason above.
     *
     * @return array<string, array{0: string}>
     */
    public static function roleProvider(): array
    {
        return [
            'customer' => ['customer'],
            'admin' => ['admin'],
            'restaurant' => ['restaurant'],
            'rider' => ['rider'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | The block every role gets
    |--------------------------------------------------------------------------
    */

    #[DataProvider('roleProvider')]
    public function test_every_role_reads_its_own_dashboard(string $role): void
    {
        [$user, $token] = $this->signedInUser($role);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', $role)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.user.role', $role)
            ->assertJsonPath('data.notifications.unread_count', 0)
            ->assertJsonStructure([
                'data' => [
                    'role',
                    'user' => ['id', 'firstName', 'lastName', 'email', 'role', 'status', 'is_email_verified', 'image_url'],
                    'notifications' => ['unread_count'],
                ],
            ]);
    }

    #[DataProvider('roleProvider')]
    public function test_the_dashboard_does_not_repeat_the_profile(string $role): void
    {
        [, $token] = $this->signedInUser($role);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200);

        foreach (['address1', 'address2', 'zip_code', 'latitude', 'longitude', 'country', 'county', 'city'] as $profileOnly) {
            $response->assertJsonMissingPath("data.user.{$profileOnly}");
        }
    }

    public function test_the_unread_badge_counts_only_the_callers_unread_notifications(): void
    {
        [$user, $token] = $this->signedInUser();
        $other = User::factory()->customer()->create();

        $this->notifications()->forUser($user)->count(3)->create();
        $this->notifications()->forUser($user)->read()->count(2)->create();
        $this->notifications()->forUser($other)->count(4)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.notifications.unread_count', 3);
    }

    /*
    |--------------------------------------------------------------------------
    | The profile picture
    |--------------------------------------------------------------------------
    */

    public function test_the_identity_block_addresses_the_picture_through_the_streaming_endpoint(): void
    {
        $user = User::factory()->customer()->create(['image' => 'avatar.webp']);
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.user.image_url', route('api.v1.media.profile-picture'))
            ->assertJsonMissingPath('data.user.image')
            ->assertDontSee('avatar.webp');
    }

    public function test_a_user_with_no_picture_gets_a_null_address_rather_than_an_empty_string(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.user.image_url', null);
    }

    /*
    |--------------------------------------------------------------------------
    | Customer
    |--------------------------------------------------------------------------
    */

    /**
     * A customer's only gate is email verification, which `user` already
     * reports — so there is deliberately no fourth key to restate it under.
     */
    public function test_a_customer_dashboard_carries_no_role_section(): void
    {
        [, $token] = $this->signedInUser('customer');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonMissingPath('data.onboarding')
            ->assertJsonMissingPath('data.users');
    }

    /*
    |--------------------------------------------------------------------------
    | Restaurant
    |--------------------------------------------------------------------------
    */

    public function test_a_restaurant_with_no_paperwork_sees_an_incomplete_gate(): void
    {
        [, $token] = $this->signedInUser('restaurant');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.onboarding.account_activated', true)
            ->assertJsonPath('data.onboarding.documents.is_complete', false)
            ->assertJsonPath('data.onboarding.documents.documents.0.on_file', false)
            ->assertJsonPath('data.onboarding.documents.documents.1.on_file', false);
    }

    public function test_a_restaurant_awaiting_approval_reports_both_gates_separately(): void
    {
        $restaurant = User::factory()->restaurant()->inactive()->create([
            'doc_image1' => 'licence.webp',
            'doc_image2' => 'identity.pdf',
        ]);
        $token = $restaurant->createToken('auth-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            // The paperwork is filed but the admin has not activated the
            // account: two independent gates, reported as two fields.
            ->assertJsonPath('data.onboarding.documents.is_complete', true)
            ->assertJsonPath('data.onboarding.account_activated', false)
            ->assertJsonPath('data.user.status', false)
            ->assertJsonPath('data.onboarding.documents.documents.1.is_pdf', true)
            ->assertDontSee('licence.webp');
    }

    /*
    |--------------------------------------------------------------------------
    | Rider
    |--------------------------------------------------------------------------
    */

    public function test_a_rider_with_no_vehicle_sees_an_unregistered_gate(): void
    {
        [, $token] = $this->signedInUser('rider');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.onboarding.vehicle_registered', false)
            ->assertJsonPath('data.onboarding.vehicle', null);
    }

    public function test_a_rider_with_a_vehicle_sees_it_on_the_dashboard(): void
    {
        [$rider, $token] = $this->signedInUser('rider');
        $vehicle = RiderVehicle::factory()->create(['rider_id' => $rider->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.onboarding.account_activated', true)
            ->assertJsonPath('data.onboarding.vehicle_registered', true)
            ->assertJsonPath('data.onboarding.vehicle.id', $vehicle->id)
            ->assertJsonPath('data.onboarding.vehicle.registration_number', $vehicle->registration_number)
            ->assertJsonPath('data.onboarding.vehicle.vehicle_brand', $vehicle->vehicle_brand);
    }

    /**
     * `RiderVehicleRepository::forRider()` takes the newest row rather than an
     * arbitrary one — nothing at the database level stops a second existing.
     */
    public function test_a_rider_with_two_vehicle_rows_sees_the_newest(): void
    {
        [$rider, $token] = $this->signedInUser('rider');

        RiderVehicle::factory()->create(['rider_id' => $rider->id]);
        $newer = RiderVehicle::factory()->create(['rider_id' => $rider->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.onboarding.vehicle.id', $newer->id);
    }

    /**
     * The vehicle belongs to the rider reading it, so its photo must be
     * addressed through the rider's own endpoint — not the admin one.
     */
    public function test_the_vehicle_photo_is_addressed_through_the_riders_own_endpoint(): void
    {
        [$rider, $token] = $this->signedInUser('rider');
        RiderVehicle::factory()->create(['rider_id' => $rider->id, 'image' => 'plate.webp']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.onboarding.vehicle.image_url', route('api.v1.rider.vehicle.image'));
    }

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    */

    public function test_an_admin_sees_a_tally_of_every_managed_role(): void
    {
        [, $token] = $this->signedInUser('admin');

        User::factory()->customer()->count(3)->create();
        User::factory()->customer()->inactive()->count(2)->create();
        User::factory()->restaurant()->create();
        User::factory()->rider()->inactive()->count(4)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.users.customer', ['total' => 5, 'active' => 3, 'inactive' => 2])
            ->assertJsonPath('data.users.restaurant', ['total' => 1, 'active' => 1, 'inactive' => 0])
            ->assertJsonPath('data.users.rider', ['total' => 4, 'active' => 0, 'inactive' => 4]);
    }

    /**
     * A role nobody has registered for must still ship a tile at zero — an
     * absent key and a zero are not the same thing to a client rendering three
     * boxes.
     */
    public function test_a_role_with_no_accounts_is_reported_as_zero_rather_than_omitted(): void
    {
        [, $token] = $this->signedInUser('admin');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.users.customer', ['total' => 0, 'active' => 0, 'inactive' => 0])
            ->assertJsonPath('data.users.restaurant', ['total' => 0, 'active' => 0, 'inactive' => 0])
            ->assertJsonPath('data.users.rider', ['total' => 0, 'active' => 0, 'inactive' => 0]);
    }

    /**
     * Admins are absent from the tally for the same reason Phase 11 made them
     * unreachable through the management lists: an admin is not somebody an
     * admin manages, and every tile here leads to one of those lists.
     */
    public function test_the_tally_does_not_count_admins(): void
    {
        [, $token] = $this->signedInUser('admin');
        User::factory()->admin()->count(3)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonMissingPath('data.users.admin')
            ->assertJsonPath('data.users.customer.total', 0);
    }

    public function test_an_admin_dashboard_carries_no_onboarding_gate(): void
    {
        [, $token] = $this->signedInUser('admin');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonMissingPath('data.onboarding');
    }

    /*
    |--------------------------------------------------------------------------
    | Cost
    |--------------------------------------------------------------------------
    */

    /**
     * The admin tally must be one grouped query, not one per role and not one
     * per account. Measured across a 30x difference in row count.
     *
     * The warm-up request is not decoration. The sanctum guard resolves the
     * token, loads the user and stamps `last_used_at` on the *first*
     * authenticated request of a test method and then memoizes — so a baseline
     * taken there measures three queries of auth handshake that the comparison
     * request never repeats, and the endpoint appears to get cheaper as rows
     * are added. Both measurements are taken after the guard is warm.
     */
    public function test_the_admin_tally_does_not_grow_with_the_number_of_accounts(): void
    {
        [, $token] = $this->signedInUser('admin');
        $request = $this->withHeader('Authorization', "Bearer {$token}");

        User::factory()->customer()->create();
        $request->getJson('/api/v1/dashboard')->assertStatus(200);

        DB::enableQueryLog();
        $request->getJson('/api/v1/dashboard')->assertStatus(200);
        $withOne = count(DB::getQueryLog());

        User::factory()->customer()->count(10)->create();
        User::factory()->restaurant()->count(10)->create();
        User::factory()->rider()->count(9)->create();

        // Flushed *after* the extra rows exist, so the factories' own inserts
        // are not counted as the endpoint's work.
        DB::flushQueryLog();

        $request->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.users.customer.total', 11);
        $withMany = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($withOne, $withMany, 'The admin dashboard grew a query as accounts were added.');
        $this->assertSame(2, $withOne, 'The admin dashboard is no longer one unread count plus one grouped tally.');
    }

    /**
     * A rider's dashboard costs the same whether or not a vehicle exists — the
     * lookup is one query either way, never a relation touched per row.
     *
     * Warmed up first, for the reason the admin measurement above records.
     */
    public function test_the_rider_dashboard_costs_the_same_with_and_without_a_vehicle(): void
    {
        [$rider, $token] = $this->signedInUser('rider');
        $request = $this->withHeader('Authorization', "Bearer {$token}");
        $request->getJson('/api/v1/dashboard')->assertStatus(200);

        DB::enableQueryLog();
        $request->getJson('/api/v1/dashboard')->assertStatus(200);
        $withNone = count(DB::getQueryLog());

        RiderVehicle::factory()->count(3)->create(['rider_id' => $rider->id]);
        DB::flushQueryLog();

        $request->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.onboarding.vehicle_registered', true);
        $withSeveral = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($withNone, $withSeveral, 'The rider dashboard grew a query per vehicle row.');
        $this->assertSame(2, $withNone, 'The rider dashboard is no longer one unread count plus one vehicle lookup.');
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_an_anonymous_caller_cannot_read_a_dashboard(): void
    {
        $this->getJson('/api/v1/dashboard')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_a_revoked_token_cannot_read_a_dashboard(): void
    {
        [$user, $token] = $this->signedInUser();
        $user->tokens()->delete();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertStatus(401);
    }
}
