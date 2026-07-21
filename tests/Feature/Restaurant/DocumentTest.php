<?php

namespace Tests\Feature\Restaurant;

use App\Http\Requests\Concerns\ValidatesUploadedDocument;
use App\Models\User;
use App\Notifications\RestaurantDocumentUpdatedNotification;
use App\Services\Media\MediaPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A restaurant's identity paperwork: filing it, reading it back, and the admin
 * read that is the first route in the codebase to name another user.
 *
 * The rules pinned here are the ones a later change could quietly break:
 *
 * - A licence never becomes a URL. It lands on the private disk and comes back
 *   only through an authenticated request from its owner or an admin.
 * - A PDF is stored as uploaded and every variant of it resolves to that file.
 * - `role:admin` is not the whole authorization question on the admin route —
 *   the bound id still has to name a restaurant.
 */
class DocumentTest extends TestCase
{
    use RefreshDatabase;
    use ValidatesUploadedDocument;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function signedIn(string $role = 'restaurant'): array
    {
        $user = User::factory()->{$role}()->create();

        return [$user, $user->createToken('auth-token')->plainTextToken];
    }

    private function scan(string $name = 'licence.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 1200, 900);
    }

    private function pdf(string $name = 'licence.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/pdf');
    }

    /**
     * Seed a filed document straight onto the disk and the row, for tests that
     * must not spend their one authenticated request uploading first.
     */
    private function fileDocument(User $restaurant, string $column, string $filename = 'on-file.jpg'): void
    {
        Storage::disk('local')->put("restaurant/document/medium/{$filename}", 'the stored scan');
        Storage::disk('local')->put("restaurant/document/large/{$filename}", 'the stored scan');
        $restaurant->forceFill([$column => $filename])->save();
    }

    public function test_a_restaurant_reads_what_it_has_on_file(): void
    {
        [$restaurant, $token] = $this->signedIn();

        $this->fileDocument($restaurant, 'doc_image1');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/restaurant/documents')
            ->assertStatus(200)
            ->assertJsonPath('data.is_complete', false)
            ->assertJsonPath('data.documents.0.slot', 1)
            ->assertJsonPath('data.documents.0.key', 'business_licence')
            ->assertJsonPath('data.documents.0.on_file', true)
            ->assertJsonPath('data.documents.1.key', 'photo_identification')
            ->assertJsonPath('data.documents.1.on_file', false)
            ->assertJsonPath('data.documents.1.url', null);
    }

    /**
     * A new restaurant has filed nothing, and that is a state to render, not an
     * error — the onboarding screen is built from this response.
     */
    public function test_a_restaurant_with_nothing_on_file_still_gets_both_slots(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/restaurant/documents')
            ->assertStatus(200)
            ->assertJsonPath('data.is_complete', false)
            ->assertJsonCount(2, 'data.documents');
    }

    /**
     * A filename is a private detail: the response says a slot is filled and
     * where to read it, never what it is called on disk.
     */
    public function test_the_response_never_exposes_a_stored_filename(): void
    {
        [$restaurant, $token] = $this->signedIn();

        $this->fileDocument($restaurant, 'doc_image1', 'secret-name.jpg');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/restaurant/documents')
            ->assertStatus(200);

        $this->assertStringNotContainsString('secret-name', $response->getContent());
        $response->assertJsonPath(
            'data.documents.0.url',
            route('api.v1.restaurant.documents.download', ['slot' => 1])
        );
    }

    public function test_filing_both_documents_completes_the_paperwork(): void
    {
        [$restaurant, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/restaurant/documents', [
                'doc_image1' => $this->scan(),
                'doc_image2' => $this->scan('id.jpg'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_complete', true)
            ->assertJsonPath('message', 'Documents uploaded. An admin will verify them and activate your account.');

        $restaurant->refresh();

        foreach (['doc_image1', 'doc_image2'] as $column) {
            $this->assertNotNull($restaurant->{$column});
            Storage::disk('local')->assertExists("restaurant/document/medium/{$restaurant->{$column}}");
        }

        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_a_correction_once_complete_answers_two_hundred(): void
    {
        [$restaurant, $token] = $this->signedIn();

        $this->fileDocument($restaurant, 'doc_image1');
        $this->fileDocument($restaurant, 'doc_image2', 'id-on-file.jpg');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/restaurant/documents', ['doc_image1' => $this->scan()])
            ->assertStatus(200)
            ->assertJsonPath('message', 'Documents updated. They will be re-verified by an admin.');
    }

    public function test_a_pdf_licence_is_accepted_and_reported_as_one(): void
    {
        [$restaurant, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/restaurant/documents', [
                'doc_image1' => $this->pdf(),
                'doc_image2' => $this->scan('id.jpg'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.documents.0.is_pdf', true)
            ->assertJsonPath('data.documents.1.is_pdf', false);

        Storage::disk('local')->assertExists("restaurant/document/original/{$restaurant->fresh()->doc_image1}");
    }

    public function test_filing_documents_notifies_every_admin(): void
    {
        [, $token] = $this->signedIn();

        $admins = User::factory()->admin()->count(2)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/restaurant/documents', [
                'doc_image1' => $this->scan(),
                'doc_image2' => $this->scan('id.jpg'),
            ])
            ->assertStatus(201);

        Notification::assertSentToTimes($admins->first(), RestaurantDocumentUpdatedNotification::class, 1);
        Notification::assertSentTo($admins->last(), RestaurantDocumentUpdatedNotification::class);
    }

    /**
     * Documents are private, so nothing that leaves the server may carry a
     * filename an interceptor could use.
     */
    public function test_the_admin_notification_names_the_kind_of_file_not_the_file(): void
    {
        [$restaurant, $token] = $this->signedIn();
        $admin = User::factory()->admin()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/restaurant/documents', [
                'doc_image1' => $this->pdf(),
                'doc_image2' => $this->scan('id.jpg'),
            ])
            ->assertStatus(201);

        Notification::assertSentTo($admin, function (RestaurantDocumentUpdatedNotification $notification) use ($admin, $restaurant): bool {
            $payload = $notification->toArray($admin);

            return $payload['documents']['business_licence']['is_pdf'] === true
                && $payload['user_id'] === $restaurant->id
                && ! str_contains(json_encode($payload), (string) $restaurant->fresh()->doc_image1);
        });
    }

    /**
     * The first submission must carry both, or the account enters verification
     * with half its paperwork.
     */
    public function test_an_empty_first_submission_is_rejected(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/restaurant/documents', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['doc_image1', 'doc_image2']);

        Notification::assertNothingSent();
    }

    /**
     * Once a slot is filled, the other may be corrected on its own — the point
     * of `requiredIf` rather than a flat `required`.
     */
    public function test_one_slot_may_be_corrected_without_resubmitting_the_other(): void
    {
        [$restaurant, $token] = $this->signedIn();

        $this->fileDocument($restaurant, 'doc_image1');
        $this->fileDocument($restaurant, 'doc_image2', 'id-on-file.jpg');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/restaurant/documents', ['doc_image2' => $this->scan('new-id.jpg')])
            ->assertStatus(200);

        $this->assertSame('on-file.jpg', $restaurant->fresh()->doc_image1);
        $this->assertNotSame('id-on-file.jpg', $restaurant->fresh()->doc_image2);
    }

    public function test_replacing_a_document_removes_the_old_file(): void
    {
        [$restaurant, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/restaurant/documents', [
                'doc_image1' => $this->scan(),
                'doc_image2' => $this->scan('id.jpg'),
            ])
            ->assertStatus(201);

        $old = $restaurant->fresh()->doc_image1;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/restaurant/documents', ['doc_image1' => $this->scan('better.jpg')])
            ->assertStatus(200);

        $new = $restaurant->fresh()->doc_image1;

        $this->assertNotSame($old, $new);

        foreach (MediaPlacement::Document->variants() as $variant) {
            Storage::disk('local')->assertMissing("restaurant/document/{$variant}/{$old}");
        }

        Storage::disk('local')->assertExists("restaurant/document/medium/{$new}");
    }

    public function test_a_file_that_is_not_an_allowed_document_is_rejected(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/restaurant/documents', [
                'doc_image1' => UploadedFile::fake()->create('sheet.xlsx', 10, 'application/vnd.ms-excel'),
                'doc_image2' => $this->scan('id.jpg'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.doc_image1.0', 'The business licence must be a jpg, jpeg, png, webp, pdf file.');
    }

    public function test_a_document_over_the_ceiling_is_rejected(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/restaurant/documents', [
                'doc_image1' => UploadedFile::fake()->create('huge.pdf', self::MAX_DOCUMENT_KILOBYTES + 1, 'application/pdf'),
                'doc_image2' => $this->scan('id.jpg'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.doc_image1.0', 'The business licence may not be larger than 4 MB.');
    }

    public function test_a_restaurant_streams_its_own_document(): void
    {
        [$restaurant, $token] = $this->signedIn();

        $this->fileDocument($restaurant, 'doc_image1');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/v1/restaurant/documents/1');

        $response->assertStatus(200);
        $this->assertSame('the stored scan', $response->streamedContent());
    }

    public function test_an_unknown_slot_is_a_not_found(): void
    {
        [$restaurant, $token] = $this->signedIn();

        $this->fileDocument($restaurant, 'doc_image1');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/restaurant/documents/9')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Resource not found.');
    }

    public function test_an_empty_slot_is_a_not_found(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/restaurant/documents/2')
            ->assertStatus(404);
    }

    /**
     * The restaurant's own download takes no id, so a second restaurant asking
     * for slot 1 is only ever asking for its own. One authenticated request
     * here on purpose: the sanctum guard memoizes the resolved user for a whole
     * test method.
     */
    public function test_the_download_serves_nobody_but_the_caller(): void
    {
        $other = User::factory()->restaurant()->create();
        $this->fileDocument($other, 'doc_image1', 'belongs-to-somebody-else.jpg');

        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/restaurant/documents/1')
            ->assertStatus(404);
    }

    public function test_an_admin_reads_a_named_restaurants_document(): void
    {
        $restaurant = User::factory()->restaurant()->create();
        $this->fileDocument($restaurant, 'doc_image1');

        [, $token] = $this->signedIn('admin');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/admin/restaurants/{$restaurant->id}/documents/1");

        $response->assertStatus(200);
        $this->assertSame('the stored scan', $response->streamedContent());
    }

    /**
     * `role:admin` proves the caller is an admin, not that the bound id names a
     * restaurant. A customer has no document slots, and the Policy is what says
     * so.
     */
    public function test_an_admin_cannot_read_documents_of_a_user_who_is_not_a_restaurant(): void
    {
        $customer = User::factory()->customer()->create();
        $this->fileDocument($customer, 'doc_image1');

        [, $token] = $this->signedIn('admin');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/admin/restaurants/{$customer->id}/documents/1")
            ->assertStatus(403);
    }

    public function test_an_unknown_restaurant_id_is_a_not_found(): void
    {
        [, $token] = $this->signedIn('admin');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/restaurants/999999/documents/1')
            ->assertStatus(404);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonRestaurantProvider(): array
    {
        return [
            'customer' => ['customer'],
            'admin' => ['admin'],
            'rider' => ['rider'],
        ];
    }

    /**
     * One case per role rather than a loop: the sanctum guard memoizes the
     * resolved user for the lifetime of a test method.
     */
    #[DataProvider('nonRestaurantProvider')]
    public function test_only_restaurants_may_read_their_document_status(string $role): void
    {
        [, $token] = $this->signedIn($role);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/restaurant/documents')
            ->assertStatus(403);
    }

    #[DataProvider('nonRestaurantProvider')]
    public function test_only_restaurants_may_file_documents(string $role): void
    {
        [, $token] = $this->signedIn($role);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/restaurant/documents', [
                'doc_image1' => $this->scan(),
                'doc_image2' => $this->scan('id.jpg'),
            ])
            ->assertStatus(403);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminProvider(): array
    {
        return [
            'customer' => ['customer'],
            'restaurant' => ['restaurant'],
            'rider' => ['rider'],
        ];
    }

    #[DataProvider('nonAdminProvider')]
    public function test_only_admins_may_reach_the_admin_read(string $role): void
    {
        $restaurant = User::factory()->restaurant()->create();
        $this->fileDocument($restaurant, 'doc_image1');

        [, $token] = $this->signedIn($role);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/admin/restaurants/{$restaurant->id}/documents/1")
            ->assertStatus(403);
    }

    public function test_reading_document_status_requires_authentication(): void
    {
        $this->getJson('/api/v1/restaurant/documents')->assertStatus(401);
    }

    public function test_filing_documents_requires_authentication(): void
    {
        $this->postJson('/api/v1/restaurant/documents', ['doc_image1' => $this->scan()])->assertStatus(401);
    }

    public function test_the_admin_read_requires_authentication(): void
    {
        $restaurant = User::factory()->restaurant()->create();

        $this->getJson("/api/v1/admin/restaurants/{$restaurant->id}/documents/1")->assertStatus(401);
    }
}
