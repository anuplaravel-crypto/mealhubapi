<?php

namespace Tests\Feature\Cms;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Media\MediaPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The admin surface over site-wide branding — the singleton half of the CMS
 * write layer.
 *
 * The rule worth pinning hardest is the one the public read established and
 * this endpoint completes: reading the settings never writes them, and saving
 * them is the only thing that ever creates the row. A freshly migrated database
 * answers both the anonymous payload and this form with the branding the site
 * shipped with, and `is_persisted` is how a client tells that fallback from a
 * row an admin actually chose.
 */
class AdminSiteSettingTest extends TestCase
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
            'site_name' => 'MealHub',
            'brand_primary_text' => 'Meal',
            'brand_accent_text' => 'Hub',
            'meta_title' => 'MealHub — Fresh Meals',
            'meta_description' => 'Personalised meal plans, delivered fresh.',
            'footer_blurb' => 'Eat better, live better.',
            ...$overrides,
        ];
    }

    /**
     * One case per role rather than a loop: the sanctum guard memoizes the
     * resolved user for the lifetime of a test method.
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

    public function test_an_admin_reads_the_saved_settings(): void
    {
        [, $token] = $this->signedIn();

        SiteSetting::factory()->create([
            'id' => SiteSetting::SINGLETON_ID,
            'site_name' => 'Saved Name',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/cms/site-settings')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.site_name', 'Saved Name')
            ->assertJsonPath('data.is_persisted', true)
            ->assertJsonStructure([
                'data' => [
                    'site_name', 'brand_primary_text', 'brand_accent_text', 'meta_title',
                    'meta_description', 'logo_url', 'footer_blurb', 'has_logo',
                    'is_persisted', 'updated_at',
                ],
            ]);
    }

    /**
     * The form is renderable against a database nobody has ever saved, and says
     * so — the values are the shipped defaults, not an admin's choices.
     */
    public function test_an_unsaved_database_answers_with_the_shipped_defaults(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/cms/site-settings')
            ->assertStatus(200)
            ->assertJsonPath('data.site_name', 'MealHub')
            ->assertJsonPath('data.brand_primary_text', 'Meal')
            ->assertJsonPath('data.brand_accent_text', 'Hub')
            ->assertJsonPath('data.is_persisted', false)
            ->assertJsonPath('data.logo_url', null);
    }

    /**
     * Reading must stay a read. MealHub's repository was a `firstOrCreate`,
     * which meant opening the page wrote a row.
     */
    public function test_reading_the_settings_does_not_create_the_row(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/cms/site-settings')
            ->assertStatus(200);

        $this->assertDatabaseCount('site_settings', 0);
    }

    /**
     * The save is the only path that inserts, and it pins the singleton id so
     * the read can find the row again.
     */
    public function test_the_first_save_creates_the_singleton_row(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/cms/site-settings', $this->validPayload([
                'site_name' => 'First Save',
            ]))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Site settings updated.')
            ->assertJsonPath('data.site_name', 'First Save')
            ->assertJsonPath('data.is_persisted', true);

        $this->assertDatabaseCount('site_settings', 1);
        $this->assertDatabaseHas('site_settings', [
            'id' => SiteSetting::SINGLETON_ID,
            'site_name' => 'First Save',
        ]);
    }

    public function test_a_second_save_updates_the_same_row(): void
    {
        [, $token] = $this->signedIn();

        SiteSetting::factory()->create(['id' => SiteSetting::SINGLETON_ID]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/cms/site-settings', $this->validPayload([
                'site_name' => 'Renamed',
            ]))
            ->assertStatus(200)
            ->assertJsonPath('data.site_name', 'Renamed');

        $this->assertDatabaseCount('site_settings', 1);
    }

    /**
     * Branding is public marketing imagery, so it goes to the public disk as
     * four variants and never touches the private one.
     */
    public function test_a_logo_upload_is_stored_in_every_variant_on_the_public_disk(): void
    {
        [, $token] = $this->signedIn();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/admin/cms/site-settings', $this->validPayload([
                'logo' => UploadedFile::fake()->image('logo.png', 500, 200),
            ]), ['Accept' => 'application/json'])
            ->assertStatus(200)
            ->assertJsonPath('data.has_logo', true);

        $filename = SiteSetting::firstOrFail()->logo;

        $this->assertNotNull($filename);

        foreach (MediaPlacement::Cms->variants() as $variant) {
            Storage::disk('public')->assertExists("cms/site/{$variant}/{$filename}");
        }

        Storage::disk('local')->assertDirectoryEmpty('/');

        $this->assertStringContainsString(
            "cms/site/small/{$filename}",
            (string) $response->json('data.logo_url'),
        );
    }

    public function test_replacing_the_logo_reclaims_the_previous_files(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/admin/cms/site-settings', $this->validPayload([
                'logo' => UploadedFile::fake()->image('first.png', 400, 200),
            ]), ['Accept' => 'application/json'])
            ->assertStatus(200);

        $old = SiteSetting::firstOrFail()->logo;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/admin/cms/site-settings', $this->validPayload([
                'logo' => UploadedFile::fake()->image('second.png', 400, 200),
            ]), ['Accept' => 'application/json'])
            ->assertStatus(200);

        $new = SiteSetting::firstOrFail()->logo;

        $this->assertNotSame($old, $new);

        foreach (MediaPlacement::Cms->variants() as $variant) {
            Storage::disk('public')->assertMissing("cms/site/{$variant}/{$old}");
            Storage::disk('public')->assertExists("cms/site/{$variant}/{$new}");
        }
    }

    /**
     * Saving without a file leaves the stored logo alone — an admin correcting
     * a typo in the meta description must not lose the branding.
     */
    public function test_saving_without_a_file_keeps_the_existing_logo(): void
    {
        [, $token] = $this->signedIn();

        $settings = SiteSetting::factory()->withLogo()->create(['id' => SiteSetting::SINGLETON_ID]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/cms/site-settings', $this->validPayload())
            ->assertStatus(200)
            ->assertJsonPath('data.has_logo', true);

        $this->assertSame($settings->logo, SiteSetting::firstOrFail()->logo);
    }

    /**
     * The admin write and the anonymous read are two halves of one feature.
     */
    public function test_a_saved_wordmark_reaches_the_public_home_payload(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/cms/site-settings', $this->validPayload([
                'site_name' => 'Published Name',
            ]))
            ->assertStatus(200);

        $this->getJson('/api/v1/home')
            ->assertStatus(200)
            ->assertJsonPath('data.site.site_name', 'Published Name');
    }

    public function test_the_name_and_primary_wordmark_are_required(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/cms/site-settings', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['site_name', 'brand_primary_text']);
    }

    public function test_an_over_long_site_name_is_rejected_before_it_reaches_the_column(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/cms/site-settings', $this->validPayload([
                'site_name' => str_repeat('a', 101),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('site_name');
    }

    public function test_an_unsupported_logo_format_is_rejected(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/admin/cms/site-settings', $this->validPayload([
                'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('logo');
    }

    public function test_an_anonymous_caller_is_unauthenticated(): void
    {
        $this->getJson('/api/v1/admin/cms/site-settings')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function test_a_non_admin_cannot_read_the_settings(string $role): void
    {
        [, $token] = $this->signedIn($role);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/cms/site-settings')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function test_a_non_admin_cannot_save_the_settings(string $role): void
    {
        [, $token] = $this->signedIn($role);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/cms/site-settings', $this->validPayload())
            ->assertStatus(403);

        $this->assertDatabaseCount('site_settings', 0);
    }
}
