<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_reset_their_password_with_a_valid_otp(): void
    {
        $user = User::factory()->create([
            'otp' => '123456',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $user->createToken('auth-token')->plainTextToken;

        $response = $this->postJson('/api/v1/reset-password', [
            'email' => $user->email,
            'otp' => '123456',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password123', $user->password));
        $this->assertNull($user->otp_expires_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_reset_fails_with_an_incorrect_otp(): void
    {
        $user = User::factory()->create([
            'otp' => '123456',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/reset-password', [
            'email' => $user->email,
            'otp' => '000000',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['otp']);
    }

    public function test_reset_fails_with_an_expired_otp(): void
    {
        $user = User::factory()->create([
            'otp' => '123456',
            'otp_expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/v1/reset-password', [
            'email' => $user->email,
            'otp' => '123456',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['otp']);
    }

    public function test_reset_requires_password_confirmation_to_match(): void
    {
        $user = User::factory()->create([
            'otp' => '123456',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/reset-password', [
            'email' => $user->email,
            'otp' => '123456',
            'password' => 'new-password123',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }
}
