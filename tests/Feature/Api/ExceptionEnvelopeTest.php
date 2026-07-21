<?php

namespace Tests\Feature\Api;

use App\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Pins the error envelope for framework-thrown exceptions. Controllers never
 * format these by hand — the handler in bootstrap/app.php reshapes every
 * api/* error into {success, message, errors}.
 */
class ExceptionEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/v1/logout')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_an_authorization_failure_returns_403(): void
    {
        Route::get('api/_test/forbidden', function (): void {
            throw new AuthorizationException('You may not view this order.');
        });

        $this->getJson('/api/_test/forbidden')
            ->assertStatus(403)
            ->assertExactJson([
                'success' => false,
                'message' => 'You may not view this order.',
            ]);
    }

    public function test_an_unknown_route_returns_404_in_the_envelope(): void
    {
        $this->getJson('/api/v1/no-such-endpoint')
            ->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Resource not found.',
            ]);
    }

    public function test_a_failed_route_model_binding_does_not_leak_the_model_class(): void
    {
        Route::get('api/_test/users/{user}', fn (User $user) => $user->id)
            ->middleware('web');

        $response = $this->getJson('/api/_test/users/999999')->assertStatus(404);

        $this->assertSame('Resource not found.', $response->json('message'));
        $this->assertStringNotContainsString('App\Models\User', $response->getContent());
    }

    public function test_a_validation_failure_returns_422_with_field_errors(): void
    {
        $this->postJson('/api/v1/registration', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors' => ['firstName', 'email', 'password']]);
    }

    public function test_rate_limiting_returns_429_with_a_retry_window(): void
    {
        $credentials = ['email' => 'nobody@example.com', 'password' => 'password123'];

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/v1/login', $credentials);
        }

        $response = $this->postJson('/api/v1/login', $credentials)->assertStatus(429);

        $response->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Too many requests. Please try again later.');

        $this->assertGreaterThan(0, $response->json('retry_after'));
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    public function test_a_domain_exception_is_rendered_with_its_own_status(): void
    {
        Route::get('api/_test/domain-failure', function (): void {
            throw new DomainException('This order can no longer be cancelled.', 409);
        });

        $this->getJson('/api/_test/domain-failure')
            ->assertStatus(409)
            ->assertExactJson([
                'success' => false,
                'message' => 'This order can no longer be cancelled.',
            ]);
    }

    public function test_a_domain_exception_can_carry_field_errors(): void
    {
        Route::get('api/_test/domain-failure-fields', function (): void {
            throw new DomainException('Coupon rejected.', 422, ['coupon' => ['This coupon has expired.']]);
        });

        $this->getJson('/api/_test/domain-failure-fields')
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'Coupon rejected.',
                'errors' => ['coupon' => ['This coupon has expired.']],
            ]);
    }

    public function test_an_unhandled_error_returns_500_without_leaking_a_stack_trace(): void
    {
        config(['app.debug' => false]);

        Route::get('api/_test/boom', function (): void {
            throw new RuntimeException('Database credentials rejected by mysql://root@127.0.0.1');
        });

        $response = $this->getJson('/api/_test/boom')->assertStatus(500);

        $response->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Server Error');

        $body = $response->getContent();

        $this->assertStringNotContainsString('mysql://', $body);
        $this->assertStringNotContainsString('trace', $body);
        $this->assertStringNotContainsString(base_path(), $body);
    }

    public function test_a_500_still_hides_the_trace_from_clients_when_debug_is_on(): void
    {
        config(['app.debug' => true]);

        Route::get('api/_test/boom-debug', function (): void {
            throw new RuntimeException('Internal detail.');
        });

        $response = $this->getJson('/api/_test/boom-debug')->assertStatus(500);

        // The envelope only ever forwards `message` and `errors`, so the
        // debug payload's exception/file/line/trace keys are dropped.
        $this->assertSame(
            ['success', 'message'],
            array_keys($response->json())
        );
    }
}
