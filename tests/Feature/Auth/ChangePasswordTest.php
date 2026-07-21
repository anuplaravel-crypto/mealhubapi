<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: string}
     */
    private function signedInUser(string $role = 'customer'): array
    {
        $user = User::factory()->create([
            'role' => $role,
            'password' => Hash::make('old-password123'),
            'is_email_verified' => true,
        ]);

        return [$user, $user->createToken('auth-token')->plainTextToken];
    }

    public function test_an_authenticated_user_can_change_their_password(): void
    {
        [$user, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/change-password', [
                'current_password' => 'old-password123',
                'password' => 'new-password456',
                'password_confirmation' => 'new-password456',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('new-password456', $user->fresh()->password));
    }

    public function test_the_new_password_works_at_login_and_the_old_one_does_not(): void
    {
        [$user, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/change-password', [
                'current_password' => 'old-password123',
                'password' => 'new-password456',
                'password_confirmation' => 'new-password456',
            ])->assertStatus(200);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'old-password123',
        ])->assertStatus(422);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'new-password456',
        ])->assertStatus(200);
    }

    public function test_other_sessions_are_revoked_but_the_current_one_survives(): void
    {
        [$user, $token] = $this->signedInUser();
        $user->createToken('phone')->plainTextToken;
        $user->createToken('tablet')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 3);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/change-password', [
                'current_password' => 'old-password123',
                'password' => 'new-password456',
                'password_confirmation' => 'new-password456',
            ])->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        // The device that made the change is still signed in.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/logout')
            ->assertStatus(200);
    }

    public function test_the_wrong_current_password_is_rejected(): void
    {
        [$user, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/change-password', [
                'current_password' => 'not-my-password',
                'password' => 'new-password456',
                'password_confirmation' => 'new-password456',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.current_password.0', 'Your current password is incorrect.');

        $this->assertTrue(Hash::check('old-password123', $user->fresh()->password));
    }

    public function test_the_new_password_must_differ_from_the_current_one(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/change-password', [
                'current_password' => 'old-password123',
                'password' => 'old-password123',
                'password_confirmation' => 'old-password123',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.password.0', 'The new password must be different from your current password.');
    }

    public function test_the_new_password_must_be_confirmed(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/change-password', [
                'current_password' => 'old-password123',
                'password' => 'new-password456',
                'password_confirmation' => 'different-password',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['password']]);
    }

    public function test_change_password_requires_authentication(): void
    {
        $this->postJson('/api/v1/change-password', [
            'current_password' => 'old-password123',
            'password' => 'new-password456',
            'password_confirmation' => 'new-password456',
        ])->assertStatus(401);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function roleEndpointProvider(): array
    {
        return [
            'admin' => ['admin', 'admin/'],
            'restaurant' => ['restaurant', 'restaurant/'],
            'rider' => ['rider', 'rider/'],
        ];
    }

    /**
     * One case per role rather than a loop in a single test: the sanctum
     * guard memoizes the resolved user for the lifetime of the test method,
     * so a second request as a *different* user would still be seen as the
     * first one.
     */
    #[DataProvider('roleEndpointProvider')]
    public function test_each_role_can_change_its_own_password(string $role, string $prefix): void
    {
        [$user, $token] = $this->signedInUser($role);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/{$prefix}change-password", [
                'current_password' => 'old-password123',
                'password' => 'new-password456',
                'password_confirmation' => 'new-password456',
            ])
            ->assertStatus(200);

        $this->assertTrue(Hash::check('new-password456', $user->fresh()->password));
    }
}
