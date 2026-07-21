<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\OtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ResendOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unverified_user_is_emailed_a_fresh_otp(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'is_email_verified' => false,
            'otp' => '111111',
            'otp_expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/v1/resend-otp', ['email' => 'jane@example.com'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $user->refresh();

        $this->assertNotSame('111111', $user->otp);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->otp);
        $this->assertTrue($user->otp_expires_at->isFuture());

        Notification::assertSentTo(
            $user,
            fn (OtpNotification $notification) => $notification->purpose === 'registration'
                && $notification->otp === $user->otp
        );
    }

    public function test_an_already_verified_user_is_not_emailed(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'is_email_verified' => true,
        ]);

        $this->postJson('/api/v1/resend-otp', ['email' => 'jane@example.com'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        Notification::assertNothingSent();
        $this->assertSame($user->otp, $user->fresh()->otp);
    }

    public function test_an_unknown_email_returns_the_same_response_as_a_known_one(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'jane@example.com', 'is_email_verified' => false]);

        $known = $this->postJson('/api/v1/resend-otp', ['email' => 'jane@example.com']);
        $unknown = $this->postJson('/api/v1/resend-otp', ['email' => 'nobody@example.com']);

        // Identical status and body — the endpoint must not be usable to
        // discover which addresses have accounts.
        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame($known->json(), $unknown->json());

        Notification::assertCount(1);
    }

    public function test_the_otp_is_not_resent_across_roles(): void
    {
        Notification::fake();

        User::factory()->create([
            'email' => 'jane@example.com',
            'role' => 'customer',
            'is_email_verified' => false,
        ]);

        // Same address, but asked for at the rider endpoint.
        $this->postJson('/api/v1/rider/resend-otp', ['email' => 'jane@example.com'])
            ->assertStatus(200);

        Notification::assertNothingSent();
    }

    public function test_resend_otp_requires_a_valid_email(): void
    {
        $this->postJson('/api/v1/resend-otp', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_resend_otp_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/v1/resend-otp', ['email' => 'jane@example.com']);
        }

        $this->postJson('/api/v1/resend-otp', ['email' => 'jane@example.com'])
            ->assertStatus(429);
    }
}
