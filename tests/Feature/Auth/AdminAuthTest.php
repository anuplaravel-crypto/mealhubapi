<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_registers_pre_verified_without_an_otp_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/admin/registration', [
            'firstName' => 'Ada',
            'email' => 'ada@example.com',
            'mobile' => '+8801700000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'admin');

        $admin = User::where('email', 'ada@example.com')->first();

        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->is_email_verified);

        Notification::assertNothingSent();
    }

    public function test_an_admin_can_login_immediately_after_registering(): void
    {
        User::factory()->create([
            'role' => 'admin',
            'email' => 'ada@example.com',
            'password' => Hash::make('password123'),
            'is_email_verified' => true,
            'status' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_admin_registration_does_not_require_terms_acceptance(): void
    {
        $response = $this->postJson('/api/v1/admin/registration', [
            'firstName' => 'Ada',
            'email' => 'ada@example.com',
            'mobile' => '+8801700000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
    }
}
