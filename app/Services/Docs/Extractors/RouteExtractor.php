<?php

namespace App\Services\Docs\Extractors;

use Illuminate\Support\Facades\Route;

class RouteExtractor
{
    /**
     * Enumerate every registered application route (skips framework routes
     * like the health-check endpoint, which has no controller).
     *
     * @return list<array{uri: string, methods: list<string>, name: ?string, controller: ?string, action: ?string, middleware: list<string>, isClosure: bool}>
     */
    public function extract(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            $isClosure = $action === 'Closure';

            $controller = null;
            $method = null;

            if (! $isClosure && str_contains($action, '@')) {
                [$controller, $method] = explode('@', $action, 2);
            }

            $methods = array_values(array_diff($route->methods(), ['HEAD']));

            $routes[] = [
                'uri' => '/'.ltrim($route->uri(), '/'),
                'methods' => $methods,
                'name' => $route->getName(),
                'controller' => $controller,
                'action' => $method,
                'middleware' => array_values($route->gatherMiddleware()),
                'isClosure' => $isClosure,
            ];
        }

        usort($routes, fn ($a, $b) => $a['uri'] <=> $b['uri']);

        return $routes;
    }

    /**
     * Unique controller::method pairs referenced by any route, in the shape
     * ControllerExtractor expects. This is the only place controllers are
     * discovered from — undocumented (unrouted) methods are intentionally
     * left out.
     *
     * @return list<array{controller: string, method: string}>
     */
    public function uniqueControllerActions(): array
    {
        $seen = [];
        $pairs = [];

        foreach ($this->extract() as $route) {
            if ($route['isClosure'] || $route['controller'] === null) {
                continue;
            }

            $key = $route['controller'].'@'.$route['action'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $pairs[] = ['controller' => $route['controller'], 'method' => $route['action']];
        }

        usort($pairs, fn ($a, $b) => $a['controller'] <=> $b['controller'] ?: $a['method'] <=> $b['method']);

        return $pairs;
    }
}
