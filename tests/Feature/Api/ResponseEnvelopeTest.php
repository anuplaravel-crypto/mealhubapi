<?php

namespace Tests\Feature\Api;

use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Pins the shape of every response controllers build through the ApiResponse
 * trait. This is the contract MealHubReact codes against — see
 * docs/features/api-conventions.md.
 */
class ResponseEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Register a throwaway api/* route that returns whatever the callback
     * builds through the trait. Phase 0 ships no endpoints of its own, so the
     * envelope is exercised here rather than through a domain controller.
     */
    private function routeReturning(callable $build): string
    {
        $responder = new class
        {
            use ApiResponse {
                successResponse as public;
                paginatedResponse as public;
                noContentResponse as public;
                errorResponse as public;
            }
        };

        $uri = 'api/_test/envelope/'.uniqid();

        Route::get($uri, fn (): JsonResponse => $build($responder));

        return '/'.$uri;
    }

    public function test_a_success_response_wraps_data_and_message(): void
    {
        $uri = $this->routeReturning(
            fn ($r) => $r->successResponse(['id' => 7], 'Fetched successfully.')
        );

        $this->getJson($uri)
            ->assertStatus(200)
            ->assertExactJson([
                'success' => true,
                'data' => ['id' => 7],
                'message' => 'Fetched successfully.',
            ]);
    }

    public function test_a_success_response_omits_the_message_key_when_none_is_given(): void
    {
        $uri = $this->routeReturning(fn ($r) => $r->successResponse(['id' => 7]));

        $this->getJson($uri)
            ->assertStatus(200)
            ->assertExactJson(['success' => true, 'data' => ['id' => 7]]);
    }

    public function test_a_success_response_honours_an_explicit_status(): void
    {
        $uri = $this->routeReturning(
            fn ($r) => $r->successResponse(['id' => 7], 'Created.', 201)
        );

        $this->getJson($uri)->assertStatus(201);
    }

    public function test_an_error_response_carries_the_status_it_is_given(): void
    {
        $uri = $this->routeReturning(
            fn ($r) => $r->errorResponse('You may not do that.', 403)
        );

        $this->getJson($uri)
            ->assertStatus(403)
            ->assertExactJson(['success' => false, 'message' => 'You may not do that.']);
    }

    public function test_an_error_response_includes_field_errors_when_given(): void
    {
        $uri = $this->routeReturning(
            fn ($r) => $r->errorResponse('The given data was invalid.', 422, ['email' => ['Taken.']])
        );

        $this->getJson($uri)
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => ['email' => ['Taken.']],
            ]);
    }

    public function test_a_paginated_response_lifts_rows_to_data_and_state_to_meta(): void
    {
        User::factory()->count(5)->create();

        $uri = $this->routeReturning(
            fn ($r) => $r->paginatedResponse(UserResource::collection(User::query()->paginate(2)))
        );

        $response = $this->getJson($uri)->assertStatus(200);

        // The client must never have to read data.data — that nesting is the
        // whole reason paginatedResponse() exists.
        $response->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 2)
            ->assertJsonPath('meta.links.prev', null);

        $this->assertNotNull($response->json('meta.links.next'));
        $this->assertSame(User::query()->orderBy('id')->first()->id, $response->json('data.0.id'));
    }

    public function test_a_paginated_response_accepts_a_bare_paginator(): void
    {
        User::factory()->count(3)->create();

        $uri = $this->routeReturning(
            fn ($r) => $r->paginatedResponse(User::query()->paginate(2), 'Users retrieved.')
        );

        $this->getJson($uri)
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('message', 'Users retrieved.')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_a_paginated_response_rejects_an_unpaginated_collection(): void
    {
        User::factory()->count(2)->create();

        $uri = $this->routeReturning(
            fn ($r) => $r->paginatedResponse(UserResource::collection(User::all()))
        );

        $this->getJson($uri)->assertStatus(500);
    }

    public function test_a_no_content_response_returns_204_with_an_empty_body(): void
    {
        $uri = $this->routeReturning(fn ($r) => $r->noContentResponse());

        $response = $this->getJson($uri)->assertStatus(204);

        $this->assertSame('', $response->getContent());
    }
}
