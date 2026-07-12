<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        // Every api/* error response (validation, auth, 404, 500, ...) is
        // reshaped into this project's {success, message, errors} envelope
        // so controllers never need to hand-format framework-thrown errors.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->is('api/*') || $response->headers->get('Content-Type') !== 'application/json') {
                return $response;
            }

            $decoded = json_decode($response->getContent(), true);

            if (! is_array($decoded)) {
                return $response;
            }

            return response()->json(array_filter([
                'success' => false,
                'message' => $decoded['message'] ?? 'An error occurred.',
                'errors' => $decoded['errors'] ?? null,
            ], fn ($value) => $value !== null), $response->getStatusCode());
        });
    })->create();
