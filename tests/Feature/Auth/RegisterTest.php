<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\OtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_register(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/registration', [
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'email' => 'jane@example.com',
            'mobile' => '+8801700000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_registration_tnc' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'jane@example.com')
            ->assertJsonPath('data.role', 'customer');

        $user = User::where('email', 'jane@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('customer', $user->role);
        $this->assertFalse($user->is_email_verified);
        $this->assertNotNull($user->otp);
        $this->assertTrue(Hash::check('password123', $user->password));

        Notification::assertSentTo($user, OtpNotification::class);
    }

    public function test_registration_requires_required_fields(): void
    {
        $response = $this->postJson('/api/v1/registration', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['firstName', 'email', 'mobile', 'password', 'accept_registration_tnc']);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->postJson('/api/v1/registration', [
            'firstName' => 'Jane',
            'email' => 'jane@example.com',
            'mobile' => '+8801700000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_registration_tnc' => true,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_registration_rejects_declined_terms(): void
    {
        $response = $this->postJson('/api/v1/registration', [
            'firstName' => 'Jane',
            'email' => 'jane@example.com',
            'mobile' => '+8801700000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_registration_tnc' => false,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['accept_registration_tnc']);
    }
}
