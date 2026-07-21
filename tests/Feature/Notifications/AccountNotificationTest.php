<?php

namespace Tests\Feature\Notifications;

use App\Events\UserStatusChanged;
use App\Models\User;
use App\Notifications\AccountStatusNotification;
use App\Notifications\RegistrationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The two notification classes this phase consolidated: who raises them, who
 * receives them, and what they store.
 *
 * `AccountStatusNotification` has no producer until Phase 11's admin toggle —
 * what is pinned here is the seam it will use, so that phase fires an event
 * rather than reaching for mail itself.
 */
class AccountNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function registrationProvider(): array
    {
        return [
            'customer' => ['customer', '/api/v1/registration'],
            'restaurant' => ['restaurant', '/api/v1/restaurant/registration'],
            'rider' => ['rider', '/api/v1/rider/registration'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationPayload(): array
    {
        return [
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'email' => 'ada@example.test',
            'mobile' => '01234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_registration_tnc' => true,
        ];
    }

    #[DataProvider('registrationProvider')]
    public function test_a_registration_notifies_every_admin(string $role, string $uri): void
    {
        Notification::fake();

        $admins = User::factory()->admin()->count(2)->create();

        $this->postJson($uri, $this->registrationPayload())->assertStatus(201);

        Notification::assertSentToTimes($admins->first(), RegistrationNotification::class, 1);
        Notification::assertSentTo($admins->last(), RegistrationNotification::class);
    }

    public function test_a_registration_stores_a_payload_the_admin_list_can_render(): void
    {
        $admin = User::factory()->admin()->create();

        $this->postJson('/api/v1/rider/registration', $this->registrationPayload())->assertStatus(201);

        $notification = $admin->notifications()->firstOrFail();

        $this->assertSame('rider_registration', $notification->data['type']);
        $this->assertSame('rider', $notification->data['role']);
        $this->assertSame('New rider registration', $notification->data['title']);
        $this->assertStringContainsString('awaiting verification', $notification->data['message']);
        $this->assertSame('Ada Lovelace', $notification->data['user']['name']);
        $this->assertSame('ada@example.test', $notification->data['user']['email']);
        $this->assertSame('Not provided', $notification->data['user']['address']);
    }

    /**
     * A customer can order the moment they verify their email, so their
     * message carries no approval clause — the one thing that genuinely
     * differed between the three per-role classes this replaced.
     */
    public function test_a_customer_registration_is_not_described_as_awaiting_verification(): void
    {
        $admin = User::factory()->admin()->create();

        $this->postJson('/api/v1/registration', $this->registrationPayload())->assertStatus(201);

        $this->assertStringNotContainsString(
            'awaiting verification',
            $admin->notifications()->firstOrFail()->data['message'],
        );
    }

    /**
     * An admin creating another admin is not news, and would notify the
     * creator about themselves.
     */
    public function test_an_admin_registration_notifies_nobody(): void
    {
        Notification::fake();

        User::factory()->admin()->create();

        $this->postJson('/api/v1/admin/registration', $this->registrationPayload())->assertStatus(201);

        Notification::assertNothingSent();
    }

    public function test_the_registration_notification_reaches_the_admin_in_app_list(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('auth-token')->plainTextToken;

        $this->postJson('/api/v1/registration', $this->registrationPayload())->assertStatus(201);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'customer_registration')
            ->assertJsonPath('data.0.read', false);
    }

    public function test_the_status_change_event_is_what_sends_the_account_status_notification(): void
    {
        Notification::fake();

        $user = User::factory()->rider()->create();

        UserStatusChanged::dispatch($user, true);

        Notification::assertSentTo($user, AccountStatusNotification::class);
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: string}>
     */
    public static function accountStatusProvider(): array
    {
        return [
            'activated customer' => ['customer', true, 'place meal orders'],
            'deactivated restaurant' => ['restaurant', false, 'receive orders'],
            'activated rider' => ['rider', true, 'accept delivery jobs'],
        ];
    }

    /**
     * The role-specific clause is read off the notifiable rather than passed
     * in, so it cannot disagree with who the notification reached.
     */
    #[DataProvider('accountStatusProvider')]
    public function test_the_account_status_payload_is_written_for_the_recipients_role(string $role, bool $activated, string $capability): void
    {
        $user = User::factory()->{$role}()->create();

        UserStatusChanged::dispatch($user, $activated);

        $notification = $user->notifications()->firstOrFail();

        $this->assertSame('account_status', $notification->data['type']);
        $this->assertSame($activated, $notification->data['activated']);
        $this->assertSame($activated ? 'Account activated' : 'Account deactivated', $notification->data['title']);
        $this->assertStringContainsString($capability, $notification->data['message']);
    }

    /**
     * Listener discovery is convention, not configuration — nothing would fail
     * loudly if the class were moved out of app/Listeners.
     */
    public function test_the_status_change_listener_is_registered(): void
    {
        $this->assertTrue(Event::hasListeners(UserStatusChanged::class));
    }
}
