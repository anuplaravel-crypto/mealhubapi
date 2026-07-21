<?php

namespace Tests\Feature\Rider;

use App\Http\Requests\Concerns\ValidatesUploadedImage;
use App\Models\RiderVehicle;
use App\Models\User;
use App\Notifications\RiderVehicleUpdatedNotification;
use App\Services\Media\MediaPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Rider onboarding's vehicle record: the upsert, the admin alert it raises, and
 * the private photo that comes back only through its owner's token.
 *
 * Two rules these tests exist to pin, both easy to regress:
 *
 * - `is_active` mirrors the rider's account status and is never the rider's to
 *   set, so a rider awaiting approval cannot ship a live-looking vehicle.
 * - A plate belongs to one vehicle in the world, so no second rider may claim
 *   one — the table's composite index does not cover that half.
 */
class VehicleTest extends TestCase
{
    use RefreshDatabase;
    use ValidatesUploadedImage;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();
    }

    /**
     * A signed-in user of the given role, with a real token — `Sanctum::actingAs()`
     * yields a `TransientToken` with no primary key, which the rest of the suite
     * avoids for the same reason.
     *
     * @return array{0: User, 1: string}
     */
    private function signedIn(string $role = 'rider', bool $approved = true): array
    {
        $user = User::factory()->{$role}();

        $user = ($approved ? $user : $user->inactive())->create();

        return [$user, $user->createToken('auth-token')->plainTextToken];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'vehicle_type' => 'bike',
            'registration_number' => 'KA-01-4477',
            'vehicle_brand' => 'Honda',
            'vehicle_model' => 'Activa',
            'vehicle_color' => 'red',
            ...$overrides,
        ];
    }

    public function test_a_rider_reads_their_registered_vehicle(): void
    {
        [$rider, $token] = $this->signedIn();

        $vehicle = RiderVehicle::factory()->car()->create(['rider_id' => $rider->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rider/vehicle')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $vehicle->id)
            ->assertJsonPath('data.vehicle_type', 'car')
            ->assertJsonPath('data.registration_number', $vehicle->registration_number)
            ->assertJsonMissingPath('data.rider_id');
    }

    public function test_a_rider_with_no_vehicle_yet_gets_a_not_found(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rider/vehicle')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You have not registered a vehicle yet.');
    }

    /**
     * The endpoint takes no id, so a rider asking for "the" vehicle is only
     * ever asking for their own. One request only: the sanctum guard memoizes
     * the resolved user for a whole test method, so seeding the other rider
     * through a request would have this one answered as them.
     */
    public function test_the_endpoint_serves_nobody_but_the_caller(): void
    {
        RiderVehicle::factory()->create(['registration_number' => 'SOMEONE-ELSE']);

        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rider/vehicle')
            ->assertStatus(404);
    }

    public function test_a_first_submission_creates_the_vehicle(): void
    {
        [$rider, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.registration_number', 'KA-01-4477')
            ->assertJsonPath('message', 'Vehicle information saved. An admin will verify your documents and activate your account.');

        $this->assertDatabaseHas('rider_vehicles', [
            'rider_id' => $rider->id,
            'vehicle_type' => 'bike',
            'registration_number' => 'KA-01-4477',
        ]);
    }

    public function test_a_second_submission_updates_the_same_row(): void
    {
        [$rider, $token] = $this->signedIn();

        RiderVehicle::factory()->create(['rider_id' => $rider->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload(['vehicle_color' => 'blue']))
            ->assertStatus(200)
            ->assertJsonPath('data.vehicle_color', 'blue')
            ->assertJsonPath('message', 'Vehicle information updated. Your documents will be re-verified by an admin.');

        $this->assertSame(1, $rider->vehicles()->count());
    }

    public function test_a_first_submission_notifies_every_admin(): void
    {
        [, $token] = $this->signedIn();

        $admins = User::factory()->admin()->count(2)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload())
            ->assertStatus(201);

        Notification::assertSentToTimes($admins->first(), RiderVehicleUpdatedNotification::class, 1);
        Notification::assertSentTo($admins->last(), RiderVehicleUpdatedNotification::class);
    }

    /**
     * An edit invalidates the verification the previous details were approved
     * under, so it goes back into the admin queue rather than passing quietly.
     */
    public function test_an_edit_notifies_the_admins_too(): void
    {
        [$rider, $token] = $this->signedIn();
        $admin = User::factory()->admin()->create();

        RiderVehicle::factory()->create(['rider_id' => $rider->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload())
            ->assertStatus(200);

        Notification::assertSentTo(
            $admin,
            fn (RiderVehicleUpdatedNotification $notification) => $notification->toArray($admin)['title'] === 'Rider vehicle updated'
        );
    }

    public function test_an_empty_submission_is_rejected(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors([
                'vehicle_type',
                'registration_number',
                'vehicle_brand',
                'vehicle_model',
                'vehicle_color',
            ]);

        $this->assertDatabaseCount('rider_vehicles', 0);
    }

    public function test_an_unknown_vehicle_type_is_rejected(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload(['vehicle_type' => 'hovercraft']))
            ->assertStatus(422)
            ->assertJsonPath('errors.vehicle_type.0', 'The vehicle type must be one of: bike, car, scooter, bicycle.');
    }

    /**
     * The table's unique index is on `[rider_id, registration_number]`, which
     * says nothing about two riders claiming the same plate. This is that half.
     */
    public function test_a_plate_already_claimed_by_another_rider_is_rejected(): void
    {
        RiderVehicle::factory()->create(['registration_number' => 'KA-01-4477']);

        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload())
            ->assertStatus(422)
            ->assertJsonPath('errors.registration_number.0', 'That registration number is already registered to another rider.');
    }

    public function test_resubmitting_your_own_unchanged_plate_is_still_valid(): void
    {
        [$rider, $token] = $this->signedIn();

        RiderVehicle::factory()->create([
            'rider_id' => $rider->id,
            'registration_number' => 'KA-01-4477',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload(['vehicle_brand' => 'Yamaha']))
            ->assertStatus(200)
            ->assertJsonPath('data.vehicle_brand', 'Yamaha');
    }

    /**
     * `is_active` mirrors `users.status` and is flipped by an admin approving
     * the account — never by the rider, and never left at the column's default
     * for an account that is not yet live.
     */
    public function test_a_rider_awaiting_approval_gets_an_inactive_vehicle(): void
    {
        [, $token] = $this->signedIn(approved: false);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_a_rider_cannot_set_the_active_flag_themselves(): void
    {
        [, $token] = $this->signedIn(approved: false);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload(['is_active' => true]))
            ->assertStatus(201)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_a_vehicle_photo_lands_on_the_private_disk_only(): void
    {
        [$rider, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload([
                'image' => UploadedFile::fake()->image('bike.jpg', 900, 900),
            ]))
            ->assertStatus(201);

        $filename = $rider->vehicles()->first()->image;

        $this->assertNotNull($filename);

        foreach (MediaPlacement::Personal->variants() as $variant) {
            Storage::disk('local')->assertExists("rider/vehicle/{$variant}/{$filename}");
        }

        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_the_response_points_at_the_streaming_endpoint(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload([
                'image' => UploadedFile::fake()->image('bike.jpg'),
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.image_url', route('api.v1.rider.vehicle.image'));
    }

    public function test_a_vehicle_without_a_photo_reports_no_image_url(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('data.image', null)
            ->assertJsonPath('data.image_url', null);
    }

    public function test_replacing_a_photo_removes_every_variant_of_the_old_one(): void
    {
        [$rider, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload([
                'image' => UploadedFile::fake()->image('first.jpg'),
            ]))
            ->assertStatus(201);

        $old = $rider->vehicles()->first()->image;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload([
                'image' => UploadedFile::fake()->image('second.jpg'),
            ]))
            ->assertStatus(200);

        $new = $rider->vehicles()->first()->image;

        $this->assertNotSame($old, $new);

        foreach (MediaPlacement::Personal->variants() as $variant) {
            Storage::disk('local')->assertMissing("rider/vehicle/{$variant}/{$old}");
            Storage::disk('local')->assertExists("rider/vehicle/{$variant}/{$new}");
        }
    }

    public function test_an_edit_without_a_photo_keeps_the_existing_one(): void
    {
        [$rider, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload([
                'image' => UploadedFile::fake()->image('bike.jpg'),
            ]))
            ->assertStatus(201);

        $filename = $rider->vehicles()->first()->image;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload(['vehicle_color' => 'green']))
            ->assertStatus(200)
            ->assertJsonPath('data.image', $filename);

        Storage::disk('local')->assertExists("rider/vehicle/medium/{$filename}");
    }

    public function test_a_file_that_is_not_an_allowed_image_is_rejected(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload([
                'image' => UploadedFile::fake()->create('logbook.pdf', 10, 'application/pdf'),
            ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.image.0', 'The vehicle photo must be an image.');

        $this->assertDatabaseCount('rider_vehicles', 0);
    }

    public function test_a_photo_over_the_ceiling_is_rejected(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload([
                'image' => UploadedFile::fake()->create('huge.jpg', self::MAX_KILOBYTES + 1, 'image/jpeg'),
            ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.image.0', 'The vehicle photo may not be larger than 2 MB.');
    }

    public function test_a_rider_streams_their_own_vehicle_photo(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload([
                'image' => UploadedFile::fake()->image('bike.jpg'),
            ]))
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/v1/rider/vehicle/image');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_the_streamed_variant_is_the_one_asked_for(): void
    {
        [$rider, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload([
                'image' => UploadedFile::fake()->image('bike.jpg', 900, 900),
            ]))
            ->assertStatus(201);

        $filename = $rider->vehicles()->first()->image;

        $small = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/v1/rider/vehicle/image?variant=small');

        $small->assertStatus(200);
        $this->assertSame(
            Storage::disk('local')->get("rider/vehicle/small/{$filename}"),
            $small->streamedContent()
        );
    }

    /**
     * Variants arrive from a query string, so an unrecognised one degrades to
     * the default rather than 404ing on a path that was never written.
     */
    public function test_an_unknown_variant_falls_back_to_the_default(): void
    {
        [$rider, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload([
                'image' => UploadedFile::fake()->image('bike.jpg'),
            ]))
            ->assertStatus(201);

        $filename = $rider->vehicles()->first()->image;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/v1/rider/vehicle/image?variant=gigantic');

        $response->assertStatus(200);
        $this->assertSame(
            Storage::disk('local')->get("rider/vehicle/medium/{$filename}"),
            $response->streamedContent()
        );
    }

    public function test_a_rider_with_no_photo_gets_a_not_found(): void
    {
        [$rider, $token] = $this->signedIn();

        RiderVehicle::factory()->create(['rider_id' => $rider->id, 'image' => null]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rider/vehicle/image')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Resource not found.');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function otherRoleProvider(): array
    {
        return [
            'customer' => ['customer'],
            'admin' => ['admin'],
            'restaurant' => ['restaurant'],
        ];
    }

    /**
     * One case per role rather than a loop: the sanctum guard memoizes the
     * resolved user for the lifetime of a test method, so a second request as a
     * different role would be answered as the first.
     */
    #[DataProvider('otherRoleProvider')]
    public function test_only_riders_may_read_the_vehicle(string $role): void
    {
        [, $token] = $this->signedIn($role);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rider/vehicle')
            ->assertStatus(403);
    }

    #[DataProvider('otherRoleProvider')]
    public function test_only_riders_may_save_a_vehicle(string $role): void
    {
        [, $token] = $this->signedIn($role);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/rider/vehicle', $this->validPayload())
            ->assertStatus(403);

        $this->assertDatabaseCount('rider_vehicles', 0);
    }

    #[DataProvider('otherRoleProvider')]
    public function test_only_riders_may_stream_a_vehicle_photo(string $role): void
    {
        [, $token] = $this->signedIn($role);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rider/vehicle/image')
            ->assertStatus(403);
    }

    public function test_reading_a_vehicle_requires_authentication(): void
    {
        $this->getJson('/api/v1/rider/vehicle')->assertStatus(401);
    }

    public function test_saving_a_vehicle_requires_authentication(): void
    {
        $this->postJson('/api/v1/rider/vehicle', $this->validPayload())->assertStatus(401);
    }

    public function test_streaming_a_photo_requires_authentication(): void
    {
        $this->getJson('/api/v1/rider/vehicle/image')->assertStatus(401);
    }
}
