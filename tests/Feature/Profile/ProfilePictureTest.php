<?php

namespace Tests\Feature\Profile;

use App\Http\Requests\Concerns\ValidatesUploadedImage;
use App\Models\User;
use App\Services\Media\MediaPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Profile pictures: the write path, and the streaming read path deferred out of
 * Phase 4 until something finally stored a private file.
 *
 * The rule these tests exist to pin is that a personal image never becomes a
 * URL — it lands on the private disk and comes back only through an
 * authenticated request from its own owner.
 */
class ProfilePictureTest extends TestCase
{
    use RefreshDatabase;
    use ValidatesUploadedImage;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function signedInUser(string $role = 'customer'): array
    {
        $user = User::factory()->{$role}()->create();

        return [$user, $user->createToken('auth-token')->plainTextToken];
    }

    public function test_a_signed_in_user_uploads_a_profile_picture(): void
    {
        [$user, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/picture', ['image' => UploadedFile::fake()->image('me.jpg', 900, 900)])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile picture updated successfully.');

        $filename = $user->fresh()->image;

        $this->assertNotNull($filename);

        foreach (MediaPlacement::Personal->variants() as $variant) {
            Storage::disk('local')->assertExists("customer/profile/{$variant}/{$filename}");
        }
    }

    public function test_a_profile_picture_never_lands_on_the_public_disk(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/picture', ['image' => UploadedFile::fake()->image('me.jpg')])
            ->assertStatus(200);

        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    /**
     * The response carries the streaming endpoint's address rather than a
     * storage URL — there is none to give for a private file.
     */
    public function test_the_response_points_at_the_streaming_endpoint(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/picture', ['image' => UploadedFile::fake()->image('me.jpg')])
            ->assertStatus(200)
            ->assertJsonPath('data.image_url', route('api.v1.media.profile-picture'));
    }

    public function test_replacing_a_picture_removes_every_variant_of_the_old_one(): void
    {
        [$user, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/picture', ['image' => UploadedFile::fake()->image('first.jpg')])
            ->assertStatus(200);

        $old = $user->fresh()->image;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/picture', ['image' => UploadedFile::fake()->image('second.jpg')])
            ->assertStatus(200);

        $new = $user->fresh()->image;

        $this->assertNotSame($old, $new);

        foreach (MediaPlacement::Personal->variants() as $variant) {
            Storage::disk('local')->assertMissing("customer/profile/{$variant}/{$old}");
            Storage::disk('local')->assertExists("customer/profile/{$variant}/{$new}");
        }
    }

    public function test_a_file_that_is_not_an_allowed_image_is_rejected(): void
    {
        [$user, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/picture', ['image' => UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf')])
            ->assertStatus(422)
            ->assertJsonPath('errors.image.0', 'The profile picture must be an image.');

        $this->assertNull($user->fresh()->image);
    }

    public function test_an_upload_over_the_ceiling_is_rejected(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/picture', [
                'image' => UploadedFile::fake()->create('huge.jpg', self::MAX_KILOBYTES + 1, 'image/jpeg'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.image.0', 'The profile picture may not be larger than 2 MB.');
    }

    public function test_the_picture_is_the_whole_payload_so_an_empty_submit_fails(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/picture', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.image.0', 'The profile picture is required.');
    }

    public function test_uploading_a_picture_requires_authentication(): void
    {
        $this->postJson('/api/v1/profile/picture', ['image' => UploadedFile::fake()->image('me.jpg')])
            ->assertStatus(401);
    }

    public function test_a_user_streams_their_own_picture(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/picture', ['image' => UploadedFile::fake()->image('me.jpg')])
            ->assertStatus(200);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/v1/media/profile-picture');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_the_streamed_variant_is_the_one_asked_for(): void
    {
        [$user, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/picture', ['image' => UploadedFile::fake()->image('me.jpg', 900, 900)])
            ->assertStatus(200);

        $filename = $user->fresh()->image;

        $small = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/v1/media/profile-picture?variant=small');

        $small->assertStatus(200);
        $this->assertSame(
            Storage::disk('local')->get("customer/profile/small/{$filename}"),
            $small->streamedContent()
        );
    }

    /**
     * Variants arrive from a query string, so an unrecognised one degrades to
     * the default rather than 404ing on a path that was never written.
     */
    public function test_an_unknown_variant_falls_back_to_the_default(): void
    {
        [$user, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/picture', ['image' => UploadedFile::fake()->image('me.jpg')])
            ->assertStatus(200);

        $filename = $user->fresh()->image;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/v1/media/profile-picture?variant=gigantic');

        $response->assertStatus(200);
        $this->assertSame(
            Storage::disk('local')->get("customer/profile/medium/{$filename}"),
            $response->streamedContent()
        );
    }

    public function test_a_user_without_a_picture_gets_a_not_found(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/media/profile-picture')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.');
    }

    /**
     * The endpoint takes no id, so there is no way to name somebody else's
     * file: a second user asking for it is only ever asking for their own, and
     * gets a 404 when they have none — never the other user's bytes.
     *
     * Only one request is made here on purpose. The sanctum guard memoizes the
     * resolved user for the lifetime of a test method, so uploading as the
     * owner first would have this second request answered as the owner.
     */
    public function test_the_endpoint_serves_nobody_but_the_caller(): void
    {
        $owner = User::factory()->create(['image' => 'belongs-to-somebody-else.jpg']);
        Storage::disk('local')->put("customer/profile/medium/{$owner->image}", 'the owner\'s bytes');

        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/media/profile-picture')
            ->assertStatus(404);
    }

    public function test_streaming_a_picture_requires_authentication(): void
    {
        $this->getJson('/api/v1/media/profile-picture')->assertStatus(401);
    }

    /**
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

    /**
     * The four roles share one `users` table, so the role has to be in the
     * storage path for the private tree to be navigable.
     */
    #[DataProvider('roleProvider')]
    public function test_each_role_stores_its_pictures_under_its_own_directory(string $role): void
    {
        [$user, $token] = $this->signedInUser($role);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/picture', ['image' => UploadedFile::fake()->image('me.jpg')])
            ->assertStatus(200);

        Storage::disk('local')->assertExists("{$role}/profile/medium/{$user->fresh()->image}");
    }
}
