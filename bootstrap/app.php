<?php

use App\Exceptions\DomainException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        // Business-rule failures raised by services. Carries its own status,
        // so services never reach for abort() or a fake ValidationException.
        $exceptions->render(function (DomainException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(array_filter([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], fn ($value) => $value !== null), $e->status());
        });

        // A failed route-model binding arrives here as a NotFoundHttpException
        // whose message is "No query results for model [App\Models\User] 5" —
        // an internal class name that must not reach a client.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        });

        // Rate limiting: lift Retry-After into the body so the client can show
        // a countdown without having to read response headers.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => (int) ($e->getHeaders()['Retry-After'] ?? 0),
            ], 429, $e->getHeaders());
        });

        // Policy and gate denials.
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'This action is unauthorized.',
            ], 403);
        });

        // Every remaining api/* error response (validation, auth, 500, ...) is
        // reshaped into this project's {success, message, errors} envelope
        // so controllers never need to hand-format framework-thrown errors.
        // Responses already enveloped by the render callbacks above pass
        // through untouched, keeping their extra keys and headers.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->is('api/*') || $response->headers->get('Content-Type') !== 'application/json') {
                return $response;
            }

            $decoded = json_decode($response->getContent(), true);

            if (! is_array($decoded) || array_key_exists('success', $decoded)) {
                return $response;
            }

            return response()->json(array_filter([
                'success' => false,
                'message' => $decoded['message'] ?? 'An error occurred.',
                'errors' => $decoded['errors'] ?? null,
            ], fn ($value) => $value !== null), $response->getStatusCode());
        });
    })->create();
