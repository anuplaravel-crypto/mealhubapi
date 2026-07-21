<?php

namespace Tests\Feature\Newsletter;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The admin newsletter surface — list and erase.
 *
 * `role:admin` is the whole authorization story here, so the tests that matter
 * most are the ones proving the other three roles cannot reach either endpoint.
 * A subscriber has no owner, which is why no Policy appears in this domain.
 */
class AdminNewsletterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: string}
     */
    private function signedInUser(string $role): array
    {
        $user = User::factory()->{$role}()->create();

        return [$user, $user->createToken('auth-token')->plainTextToken];
    }

    /**
     * The three roles that must be turned away, one case each — a data provider
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

    public function test_an_admin_reads_the_subscriber_list(): void
    {
        [, $token] = $this->signedInUser('admin');
        NewsletterSubscriber::factory()->count(3)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/newsletter')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonStructure([
                'data' => [['id', 'email', 'status', 'is_mailable', 'confirmed_at', 'unsubscribed_at', 'created_at']],
                'meta' => ['current_page', 'per_page', 'last_page', 'total', 'from', 'to', 'links'],
            ]);
    }

    public function test_the_list_reports_each_derived_status(): void
    {
        [, $token] = $this->signedInUser('admin');
        NewsletterSubscriber::factory()->create(['email' => 'pending@example.com']);
        NewsletterSubscriber::factory()->confirmed()->create(['email' => 'confirmed@example.com']);
        NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'gone@example.com']);

        $rows = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/newsletter')
            ->assertStatus(200)
            ->json('data');

        $byEmail = collect($rows)->keyBy('email');

        // Derived from the two timestamps rather than stored, so an admin can
        // never be shown a status that disagrees with them.
        $this->assertSame('pending', $byEmail['pending@example.com']['status']);
        $this->assertSame('confirmed', $byEmail['confirmed@example.com']['status']);
        $this->assertSame('unsubscribed', $byEmail['gone@example.com']['status']);
        $this->assertTrue($byEmail['confirmed@example.com']['is_mailable']);
        $this->assertFalse($byEmail['gone@example.com']['is_mailable']);
    }

    public function test_the_list_is_newest_first(): void
    {
        [, $token] = $this->signedInUser('admin');
        NewsletterSubscriber::factory()->create(['email' => 'older@example.com', 'created_at' => now()->subDay()]);
        NewsletterSubscriber::factory()->create(['email' => 'newer@example.com', 'created_at' => now()]);

        // BaseRepository::paginate() applies no ordering; an unordered
        // paginated list is not stable across pages.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/newsletter')
            ->assertStatus(200)
            ->assertJsonPath('data.0.email', 'newer@example.com')
            ->assertJsonPath('data.1.email', 'older@example.com');
    }

    public function test_the_list_never_exposes_a_token(): void
    {
        [, $token] = $this->signedInUser('admin');
        $subscriber = NewsletterSubscriber::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/newsletter')
            ->assertStatus(200);

        // One screenshot of this list would otherwise hand out the credential
        // that confirms or unsubscribes every address on it.
        $this->assertStringNotContainsString($subscriber->token, $response->getContent());
    }

    public function test_the_list_does_not_scale_its_query_count_with_rows(): void
    {
        [, $token] = $this->signedInUser('admin');
        NewsletterSubscriber::factory()->count(15)->create();

        DB::enableQueryLog();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/newsletter')
            ->assertStatus(200);

        // Three of these are Sanctum's (token, user, the last_used_at touch)
        // and two are the paginator's count and page. Nothing per row: the
        // Resource reads derived attributes, not relations.
        $this->assertCount(5, DB::getQueryLog());

        DB::disableQueryLog();
    }

    public function test_an_admin_erases_a_subscriber(): void
    {
        [, $token] = $this->signedInUser('admin');
        $subscriber = NewsletterSubscriber::factory()->confirmed()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/admin/newsletter/{$subscriber->id}")
            ->assertStatus(204)
            ->assertNoContent();

        // Erasure, unlike unsubscribing, forgets the address entirely — so a
        // later signup for it starts clean.
        $this->assertDatabaseMissing('newsletter_subscribers', ['id' => $subscriber->id]);
    }

    public function test_erasing_an_unknown_subscriber_is_a_404(): void
    {
        [, $token] = $this->signedInUser('admin');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/admin/newsletter/99999')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.');
    }

    public function test_the_list_rejects_an_unauthenticated_caller(): void
    {
        $this->getJson('/api/v1/admin/newsletter')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_deleting_rejects_an_unauthenticated_caller(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->deleteJson("/api/v1/admin/newsletter/{$subscriber->id}")
            ->assertStatus(401);

        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $subscriber->id]);
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function test_the_list_rejects_a_token_of_the_wrong_role(string $role): void
    {
        [, $token] = $this->signedInUser($role);
        NewsletterSubscriber::factory()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/newsletter')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    #[DataProvider('nonAdminRoleProvider')]
    public function test_deleting_rejects_a_token_of_the_wrong_role(string $role): void
    {
        [, $token] = $this->signedInUser($role);
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/admin/newsletter/{$subscriber->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $subscriber->id]);
    }
}
