<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_verify_their_email_with_a_valid_otp(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => false,
            'otp' => '123456',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/verify-otp', [
            'email' => $user->email,
            'otp' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertTrue($user->fresh()->is_email_verified);
        $this->assertNull($user->fresh()->otp_expires_at);
    }

    public function test_verification_fails_with_an_incorrect_otp(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => false,
            'otp' => '123456',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/verify-otp', [
            'email' => $user->email,
            'otp' => '999999',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['otp']);
        $this->assertFalse($user->fresh()->is_email_verified);
    }

    public function test_verification_fails_with_an_expired_otp(): void
    {
        $user = User::factory()->create([
            'is_email_verified' => false,
            'otp' => '123456',
            'otp_expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/v1/verify-otp', [
            'email' => $user->email,
            'otp' => '123456',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['otp']);
    }

    public function test_verification_requires_a_known_email(): void
    {
        $response = $this->postJson('/api/v1/verify-otp', [
            'email' => 'unknown@example.com',
            'otp' => '123456',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }
}
