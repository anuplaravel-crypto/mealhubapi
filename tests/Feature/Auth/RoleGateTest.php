<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The `role:` middleware. Before it existed, `auth:sanctum` proved only that
 * a token was valid — so a customer's token could call an admin endpoint,
 * because all four roles share one `users` table and one token type.
 */
class RoleGateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function crossRoleRouteProvider(): array
    {
        return [
            'customer token at admin logout' => ['customer', '/api/v1/admin/logout'],
            'customer token at restaurant logout' => ['customer', '/api/v1/restaurant/logout'],
            'customer token at rider logout' => ['customer', '/api/v1/rider/logout'],
            'admin token at customer logout' => ['admin', '/api/v1/logout'],
            'rider token at admin logout' => ['rider', '/api/v1/admin/logout'],
            'restaurant token at admin change-password' => ['restaurant', '/api/v1/admin/change-password'],
        ];
    }

    #[DataProvider('crossRoleRouteProvider')]
    public function test_a_token_is_rejected_at_another_roles_endpoint(string $role, string $uri): void
    {
        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson($uri)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This action is not available for your account type.');
    }

    public function test_a_token_is_accepted_at_its_own_roles_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/logout')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_a_role_gated_route_still_returns_401_when_unauthenticated(): void
    {
        // The gate must not turn a missing token into a 403 — that would tell
        // an anonymous caller the route exists for *some* role.
        $this->postJson('/api/v1/admin/logout')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_the_cross_role_call_does_not_revoke_the_callers_token(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $token = $customer->createToken('auth-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/logout')
            ->assertStatus(403);

        // The gate runs before the controller, so nothing was mutated.
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/logout')
            ->assertStatus(200);
    }
}
