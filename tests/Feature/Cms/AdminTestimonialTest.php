<?php

namespace Tests\Feature\Cms;

use App\Models\Testimonial;
use App\Models\User;
use App\Services\Media\MediaPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The admin surface over the review carousel — the pattern-setter for the six
 * reorderable CMS collections that follow it.
 *
 * What these tests pin, beyond the five endpoints working:
 *
 * - The admin list shows what the public payload hides. An unpublished review
 *   is absent from `v1/home` entirely, so a list that filtered the same way
 *   would leave an admin unable to find the row they just took down.
 * - An upload wins over a link, and the link is cleared. Leaving a stale
 *   `avatar_url` behind stores a value that can never be shown and would
 *   silently reappear if the upload were ever removed.
 * - Replacing or deleting a review reclaims its files. The filename is the only
 *   reference to four variants, so losing it orphans all four.
 * - `role:admin` is the whole authorization story — a testimonial has no owner,
 *   which is why this domain has no Policy.
 */
class AdminTestimonialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    /**
     * A signed-in user of the given role, with a real token — `Sanctum::actingAs()`
     * yields a `TransientToken` with no primary key, which the rest of the suite
     * avoids for the same reason.
     *
     * @return array{0: User, 1: string}
     */
    private function signedIn(string $role = 'admin'): array
    {
        $user = User::factory()->{$role}()->create();

        return [$user, $user->createToken('auth-token')->plainTextToken];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'quote' => 'The rider arrived before the app finished animating.',
            'author_name' => 'Asha Menon',
            'author_role' => 'Weekly subscriber',
            'rating' => 4.5,
            ...$overrides,
        ];
    }

    /**
     * The three roles the gate must turn away, one case each — a data provider
     * rather than a loop, because the sanctum guard memoizes the resolved user
     * for the lifetime of a test method and a second authenticated request
     * would answer as the first user.
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
     * @return array<string, array{0: string, 1: string}>
     */
    public static function writeEndpointProvider(): array
    {
        return [
            'store' => ['post', '/api/v1/admin/cms/testimonials'],
            'update' => ['post', '/api/v1/admin/cms/testimonials/1'],
            'toggle' => ['patch', '/api/v1/admin/cms/testimonials/1/toggle'],
            'destroy' => ['delete', '/api/v1/admin/cms/testimonials/1'],
        ];
    }

    public function test_an_admin_reads_every_review_in_display_order(): void
    {
        [, $token] = $this->signedIn();

        $second = Testimonial::factory()->create(['sort_order' => 2]);
        $first = Testimonial::factory()->create(['sort_order' => 1]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/cms/testimonials')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonStructure([
                'data' => [[
                    'id', 'quote', 'author_name', 'author_role', 'avatar_url', 'rating',
                    'sort_order', 'is_published', 'has_uploaded_avatar', 'external_avatar_url',
                    'created_at', 'updated_at',
                ]],
            ]);
    }

    /**
     * The public payload drops an unpublished review; the admin list must not,
     * or an admin could never find the row they just hid in order to restore it.
     */
    public function test_the_admin_list_includes_unpublished_reviews(): void
    {
        [, $token] = $this->signedIn();

        $hidden = Testimonial::factory()->unpublished()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/cms/testimonials')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $hidden->id)
            ->assertJsonPath('data.0.is_published', false);
    }

    /**
     * The list is one query no matter how many rows it returns. It is not
     * paginated, so an eager-load forgotten on a future CMS collection would
     * show up here as N+1 rather than as a slow page nobody measured.
     */
    public function test_the_list_does_not_scale_its_query_count_with_the_row_count(): void
    {
        [, $token] = $this->signedIn();

        Testimonial::factory()->count(12)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/cms/testimonials')
            ->assertStatus(200);

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Three fixed costs every authenticated request pays — the token
        // lookup, its user, and Sanctum's `last_used_at` write — plus the one
        // query that actually reads the list.
        $this->assertSame(4, $count);
    }

    public function test_an_admin_creates_a_review(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/cms/testimonials', $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Testimonial created.')
            ->assertJsonPath('data.author_name', 'Asha Menon')
            ->assertJsonPath('data.rating', '4.5')
            ->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('testimonials', ['author_name' => 'Asha Menon']);
    }

    /**
     * A new review lands at the end of the carousel rather than sharing
     * position 0 with whatever is already there.
     */
    public function test_a_created_review_is_appended_to_the_running_order(): void
    {
        [, $token] = $this->signedIn();

        Testimonial::factory()->create(['sort_order' => 7]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/cms/testimonials', $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('data.sort_order', 8);
    }

    public function test_an_explicit_sort_order_is_respected(): void
    {
        [, $token] = $this->signedIn();

        Testimonial::factory()->create(['sort_order' => 7]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/cms/testimonials', $this->validPayload(['sort_order' => 2]))
            ->assertStatus(201)
            ->assertJsonPath('data.sort_order', 2);
    }

    public function test_an_uploaded_avatar_is_stored_in_every_variant_and_clears_the_external_link(): void
    {
        [, $token] = $this->signedIn();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/admin/cms/testimonials', $this->validPayload([
                'avatar' => UploadedFile::fake()->image('face.jpg', 600, 600),
                'avatar_url' => 'https://example.test/face.jpg',
            ]), ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('data.has_uploaded_avatar', true)
            ->assertJsonPath('data.external_avatar_url', null);

        $filename = Testimonial::firstOrFail()->avatar;

        $this->assertNotNull($filename);

        foreach (MediaPlacement::Cms->variants() as $variant) {
            Storage::disk('public')->assertExists("cms/testimonials/{$variant}/{$filename}");
        }

        // Public marketing imagery never lands on the private disk.
        Storage::disk('local')->assertDirectoryEmpty('/');

        $this->assertStringContainsString(
            "cms/testimonials/small/{$filename}",
            (string) $response->json('data.avatar_url'),
        );
    }

    /**
     * With no upload, the external link is what the Resource resolves to, and
     * it survives the save untouched.
     */
    public function test_an_external_avatar_link_survives_when_no_file_is_uploaded(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/cms/testimonials', $this->validPayload([
                'avatar_url' => 'https://example.test/face.jpg',
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.has_uploaded_avatar', false)
            ->assertJsonPath('data.external_avatar_url', 'https://example.test/face.jpg')
            ->assertJsonPath('data.avatar_url', 'https://example.test/face.jpg');
    }

    public function test_an_admin_edits_a_review(): void
    {
        [, $token] = $this->signedIn();

        $testimonial = Testimonial::factory()->create(['author_name' => 'Before']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/admin/cms/testimonials/{$testimonial->id}", $this->validPayload([
                'author_name' => 'After',
            ]))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Testimonial updated.')
            ->assertJsonPath('data.id', $testimonial->id)
            ->assertJsonPath('data.author_name', 'After');

        $this->assertDatabaseHas('testimonials', ['id' => $testimonial->id, 'author_name' => 'After']);
    }

    /**
     * The outgoing file's variants go only after the new ones are written —
     * otherwise a failure mid-encode leaves the row pointing at nothing.
     */
    public function test_replacing_an_avatar_reclaims_the_previous_files(): void
    {
        [, $token] = $this->signedIn();

        $testimonial = Testimonial::factory()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/v1/admin/cms/testimonials/{$testimonial->id}", $this->validPayload([
                'avatar' => UploadedFile::fake()->image('first.jpg', 400, 400),
            ]), ['Accept' => 'application/json'])
            ->assertStatus(200);

        $old = $testimonial->fresh()->avatar;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/v1/admin/cms/testimonials/{$testimonial->id}", $this->validPayload([
                'avatar' => UploadedFile::fake()->image('second.jpg', 400, 400),
            ]), ['Accept' => 'application/json'])
            ->assertStatus(200);

        $new = $testimonial->fresh()->avatar;

        $this->assertNotSame($old, $new);

        foreach (MediaPlacement::Cms->variants() as $variant) {
            Storage::disk('public')->assertMissing("cms/testimonials/{$variant}/{$old}");
            Storage::disk('public')->assertExists("cms/testimonials/{$variant}/{$new}");
        }
    }

    public function test_editing_an_unknown_review_is_a_not_found(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/cms/testimonials/9999', $this->validPayload())
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.');
    }

    public function test_the_toggle_hides_and_restores_a_review(): void
    {
        [, $token] = $this->signedIn();

        $testimonial = Testimonial::factory()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/admin/cms/testimonials/{$testimonial->id}/toggle")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Testimonial hidden.')
            ->assertJsonPath('data.is_published', false);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/admin/cms/testimonials/{$testimonial->id}/toggle")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Testimonial published.')
            ->assertJsonPath('data.is_published', true);
    }

    /**
     * The admin write and the anonymous read are two halves of one feature, so
     * hiding a review has to actually take it off the public page.
     */
    public function test_hiding_a_review_removes_it_from_the_public_home_payload(): void
    {
        [, $token] = $this->signedIn();

        $testimonial = Testimonial::factory()->create();

        $this->getJson('/api/v1/home')->assertJsonCount(1, 'data.testimonials');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/admin/cms/testimonials/{$testimonial->id}/toggle")
            ->assertStatus(200);

        $this->getJson('/api/v1/home')->assertJsonCount(0, 'data.testimonials');
    }

    public function test_toggling_an_unknown_review_is_a_not_found(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/admin/cms/testimonials/9999/toggle')
            ->assertStatus(404);
    }

    public function test_an_admin_deletes_a_review_and_its_files(): void
    {
        [, $token] = $this->signedIn();

        $testimonial = Testimonial::factory()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/v1/admin/cms/testimonials/{$testimonial->id}", $this->validPayload([
                'avatar' => UploadedFile::fake()->image('face.jpg', 400, 400),
            ]), ['Accept' => 'application/json'])
            ->assertStatus(200);

        $filename = $testimonial->fresh()->avatar;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/admin/cms/testimonials/{$testimonial->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('testimonials', ['id' => $testimonial->id]);

        foreach (MediaPlacement::Cms->variants() as $variant) {
            Storage::disk('public')->assertMissing("cms/testimonials/{$variant}/{$filename}");
        }
    }

    public function test_deleting_an_unknown_review_is_a_not_found(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/admin/cms/testimonials/9999')
            ->assertStatus(404);
    }

    public function test_a_review_needs_a_quote_an_author_and_a_rating(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/cms/testimonials', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['quote', 'author_name', 'rating']);
    }

    public function test_a_rating_outside_the_star_range_is_rejected(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/cms/testimonials', $this->validPayload(['rating' => 6]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');
    }

    /**
     * SVG is refused on the public disk specifically because it is an XSS
     * vector there — see the shared upload trait.
     */
    public function test_an_unsupported_avatar_format_is_rejected(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/admin/cms/testimonials', $this->validPayload([
                'avatar' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar');
    }

    public function test_an_anonymous_caller_is_unauthenticated(): void
    {
        $this->getJson('/api/v1/admin/cms/testimonials')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function test_a_non_admin_cannot_read_the_list(string $role): void
    {
        [, $token] = $this->signedIn($role);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/cms/testimonials')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function test_a_non_admin_cannot_create_a_review(string $role): void
    {
        [, $token] = $this->signedIn($role);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/cms/testimonials', $this->validPayload())
            ->assertStatus(403);

        $this->assertDatabaseCount('testimonials', 0);
    }

    /**
     * The role gate sits on the route group, so every write is covered — but a
     * gate that only guarded the list would still pass the test above.
     *
     * @param  'post'|'patch'|'delete'  $method
     */
    #[DataProvider('writeEndpointProvider')]
    public function test_every_write_endpoint_is_behind_the_admin_gate(string $method, string $uri): void
    {
        Testimonial::factory()->create(['id' => 1]);

        [, $token] = $this->signedIn('customer');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->{$method.'Json'}($uri, $this->validPayload())
            ->assertStatus(403);
    }
}
