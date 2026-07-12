<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\OtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_emails_an_otp_for_a_known_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'jane@example.com',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        $this->assertNotNull($user->fresh()->otp_expires_at);
        Notification::assertSentTo($user, OtpNotification::class);
    }

    public function test_it_returns_a_generic_response_for_an_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'unknown@example.com',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        Notification::assertNothingSent();
    }

    public function test_it_requires_a_valid_email_format(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }
}
