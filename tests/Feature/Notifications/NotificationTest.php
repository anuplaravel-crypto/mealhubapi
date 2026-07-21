<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Database\Factories\DatabaseNotificationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The six in-app notification endpoints.
 *
 * These are the first routes in the codebase where an id arrives from the URL,
 * so the thing that most needs pinning is that the id cannot be pointed at
 * somebody else's row — on every one of the four that take one.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
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
     * The four roles, one case each — a data provider rather than a loop
     * because the sanctum guard memoizes the resolved user for the lifetime of
     * a test method, so a second authenticated request would answer as the
     * first user.
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

    #[DataProvider('roleProvider')]
    public function test_every_role_reads_its_own_notification_list(string $role): void
    {
        [$user, $token] = $this->signedInUser($role);
        $this->notifications()->forUser($user)->count(3)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_the_list_carries_the_lifted_payload_keys_and_the_raw_data(): void
    {
        [$user, $token] = $this->signedInUser();
        $notification = $this->notifications()->forUser($user)->accountStatus()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.type', 'account_status')
            ->assertJsonPath('data.0.title', 'Account activated')
            ->assertJsonPath('data.0.read', false)
            ->assertJsonPath('data.0.read_at', null)
            ->assertJsonPath('data.0.data.activated', true);
    }

    /**
     * `type` must be the semantic token from the payload, never the notification
     * class name the column actually stores.
     */
    public function test_the_list_never_leaks_the_notification_class_name(): void
    {
        [$user, $token] = $this->signedInUser();
        $this->notifications()->forUser($user)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications')
            ->assertStatus(200)
            ->assertDontSee('App\\\\Notifications', escape: false);
    }

    public function test_the_list_is_newest_first(): void
    {
        [$user, $token] = $this->signedInUser();

        $older = $this->notifications()->forUser($user)->create(['created_at' => now()->subDay()]);
        $newer = $this->notifications()->forUser($user)->create(['created_at' => now()]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_the_list_is_paginated_at_fifteen_rows(): void
    {
        [$user, $token] = $this->signedInUser();
        $this->notifications()->forUser($user)->count(20)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications?page=2')
            ->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 20);
    }

    public function test_the_list_excludes_other_users_notifications(): void
    {
        [$user, $token] = $this->signedInUser();
        $this->notifications()->forUser($user)->create();
        $this->notifications()->forUser(User::factory()->create())->count(4)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    /**
     * A list endpoint is where an N+1 would hide, so pin the count. Five, and
     * flat in the number of rows: three are Sanctum's (token, user, and the
     * `last_used_at` touch) and two are the paginator's (count, then page).
     */
    public function test_the_list_runs_a_fixed_number_of_queries(): void
    {
        [$user, $token] = $this->signedInUser();
        $this->notifications()->forUser($user)->count(12)->create();

        DB::enableQueryLog();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications')
            ->assertStatus(200);

        $this->assertCount(5, DB::getQueryLog());
    }

    public function test_an_empty_list_is_an_empty_array_rather_than_a_missing_key(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications')
            ->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_the_unread_endpoint_counts_every_unread_but_returns_at_most_twenty(): void
    {
        [$user, $token] = $this->signedInUser();
        $this->notifications()->forUser($user)->count(25)->create();
        $this->notifications()->forUser($user)->read()->count(3)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications/unread')
            ->assertStatus(200)
            ->assertJsonPath('data.count', 25)
            ->assertJsonCount(20, 'data.notifications');
    }

    public function test_the_unread_endpoint_ignores_read_and_other_users_notifications(): void
    {
        [$user, $token] = $this->signedInUser();
        $this->notifications()->forUser($user)->read()->count(2)->create();
        $this->notifications()->forUser(User::factory()->create())->count(5)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications/unread')
            ->assertStatus(200)
            ->assertJsonPath('data.count', 0)
            ->assertJsonCount(0, 'data.notifications');
    }

    public function test_a_user_marks_their_own_notification_as_read(): void
    {
        [$user, $token] = $this->signedInUser();
        $notification = $this->notifications()->forUser($user)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertStatus(200)
            ->assertJsonPath('data.read', true)
            ->assertJsonPath('message', 'Notification marked as read.');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    /**
     * A client that retries must not move the timestamp: "when did I read
     * this" is the only thing `read_at` is good for.
     */
    public function test_marking_an_already_read_notification_is_idempotent(): void
    {
        [$user, $token] = $this->signedInUser();
        $notification = $this->notifications()->forUser($user)->read()->create();
        $readAt = $notification->read_at;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertStatus(200)
            ->assertJsonPath('data.read', true);

        $this->assertTrue($readAt->equalTo($notification->fresh()->read_at));
    }

    public function test_toggling_flips_an_unread_notification_to_read(): void
    {
        [$user, $token] = $this->signedInUser();
        $notification = $this->notifications()->forUser($user)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/notifications/{$notification->id}/toggle-read")
            ->assertStatus(200)
            ->assertJsonPath('data.read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_toggling_flips_a_read_notification_back_to_unread(): void
    {
        [$user, $token] = $this->signedInUser();
        $notification = $this->notifications()->forUser($user)->read()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/notifications/{$notification->id}/toggle-read")
            ->assertStatus(200)
            ->assertJsonPath('data.read', false)
            ->assertJsonPath('data.read_at', null);

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_clears_only_the_callers_unread_notifications(): void
    {
        [$user, $token] = $this->signedInUser();
        $other = User::factory()->create();

        $this->notifications()->forUser($user)->count(3)->create();
        $this->notifications()->forUser($user)->read()->create();
        $this->notifications()->forUser($other)->count(2)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/notifications/read-all')
            ->assertStatus(200)
            ->assertJsonPath('data.marked', 3);

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(2, $other->unreadNotifications()->count());
    }

    public function test_mark_all_as_read_with_nothing_unread_reports_zero(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/notifications/read-all')
            ->assertStatus(200)
            ->assertJsonPath('data.marked', 0);
    }

    public function test_a_user_deletes_their_own_notification(): void
    {
        [$user, $token] = $this->signedInUser();
        $notification = $this->notifications()->forUser($user)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertStatus(204)
            ->assertNoContent();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    /**
     * The ownership check on each id-taking endpoint. The victim's notification
     * is seeded directly rather than through a request as that user — the
     * sanctum guard memoizes the first resolved user for the whole test method,
     * so a two-request version would pass for the wrong reason.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function otherUsersNotificationProvider(): array
    {
        return [
            'mark as read' => ['patchJson', '/read'],
            'toggle read' => ['patchJson', '/toggle-read'],
            'delete' => ['deleteJson', ''],
        ];
    }

    #[DataProvider('otherUsersNotificationProvider')]
    public function test_a_user_cannot_touch_another_users_notification(string $method, string $suffix): void
    {
        [, $token] = $this->signedInUser();
        $theirs = $this->notifications()->forUser(User::factory()->create())->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->{$method}("/api/v1/notifications/{$theirs->id}{$suffix}")
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('notifications', ['id' => $theirs->id, 'read_at' => null]);
    }

    public function test_an_unknown_notification_id_is_a_404(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/notifications/'.fake()->uuid().'/read')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.');
    }

    /**
     * @return array<string, array{0: string,1: string}>
     */
    public static function endpointProvider(): array
    {
        return [
            'index' => ['getJson', '/api/v1/notifications'],
            'unread' => ['getJson', '/api/v1/notifications/unread'],
            'read all' => ['patchJson', '/api/v1/notifications/read-all'],
            // A syntactically valid id that does not exist: the 401 must come
            // before the lookup, or an anonymous caller could probe for ids.
            'mark as read' => ['patchJson', '/api/v1/notifications/9f1b0c2e-0000-4000-8000-000000000000/read'],
            'delete' => ['deleteJson', '/api/v1/notifications/9f1b0c2e-0000-4000-8000-000000000000'],
        ];
    }

    #[DataProvider('endpointProvider')]
    public function test_every_endpoint_rejects_an_unauthenticated_caller(string $method, string $uri): void
    {
        $this->{$method}($uri)
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }
}
