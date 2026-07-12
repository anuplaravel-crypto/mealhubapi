<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_cannot_login_at_the_admin_endpoint(): void
    {
        User::factory()->create([
            'role' => 'customer',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
            'is_email_verified' => true,
            'status' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_an_admin_cannot_login_at_the_customer_endpoint(): void
    {
        User::factory()->create([
            'role' => 'admin',
            'email' => 'ada@example.com',
            'password' => Hash::make('password123'),
            'is_email_verified' => true,
            'status' => true,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_a_restaurant_can_login_at_its_own_endpoint(): void
    {
        User::factory()->create([
            'role' => 'restaurant',
            'email' => 'resto@example.com',
            'password' => Hash::make('password123'),
            'is_email_verified' => true,
            'status' => true,
        ]);

        $response = $this->postJson('/api/v1/restaurant/login', [
            'email' => 'resto@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.user.role', 'restaurant');
    }
}
