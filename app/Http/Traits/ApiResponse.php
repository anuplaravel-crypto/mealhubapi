<?php

namespace App\Http\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use InvalidArgumentException;

trait ApiResponse
{
    /**
     * Build a successful API response following the project's envelope.
     */
    protected function successResponse(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = ['success' => true, 'data' => $data];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }

    /**
     * Build a successful API response for a paginated list.
     *
     * Laravel's own paginated resource response is `{data, links, meta}`. Handing
     * that to successResponse() would nest it under `data`, so the client would
     * read `data.data[0]`. This lifts the rows to `data` and the paginator state
     * to a sibling `meta` key, keeping list responses the same shape as every
     * other success response.
     *
     * @param  LengthAwarePaginator<int, mixed>|ResourceCollection  $paginated
     *
     * @throws InvalidArgumentException when the collection is not paginated
     */
    protected function paginatedResponse(LengthAwarePaginator|ResourceCollection $paginated, ?string $message = null, int $status = 200): JsonResponse
    {
        $paginator = $paginated instanceof ResourceCollection ? $paginated->resource : $paginated;

        if (! $paginator instanceof LengthAwarePaginator) {
            throw new InvalidArgumentException('paginatedResponse() expects a paginated collection.');
        }

        $payload = [
            'success' => true,
            'data' => $paginated instanceof ResourceCollection
                ? ($paginated->response()->getData(true)['data'] ?? [])
                : $paginator->items(),
            'meta' => $this->paginationMeta($paginator),
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }

    /**
     * Build an empty success response for actions with nothing to return
     * (deletes, and any other command-style endpoint).
     */
    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Build a failed API response following the project's envelope.
     *
     * The status is required rather than defaulted: a forgotten argument used
     * to silently return 422 for what were really 400/403/404 conditions.
     *
     * @param  array<string, list<string>>|null  $errors
     */
    protected function errorResponse(string $message, int $status, ?array $errors = null): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * The single `meta` object every paginated response carries, documented in
     * docs/features/api-conventions.md.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array<string, mixed>
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];
    }
}
