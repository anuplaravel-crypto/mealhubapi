<?php

namespace App\Services\Docs\Renderers;

use App\Services\Docs\Support\MarkdownBuilder as MD;

class RoutesRenderer
{
    /**
     * @param  list<array{uri: string, methods: list<string>, name: ?string, controller: ?string, action: ?string, middleware: list<string>, isClosure: bool}>  $routes
     */
    public function render(array $routes): string
    {
        // Role-prefixed groups sit under the /api/v1 version prefix; anything
        // left over is the customer (root) or a framework/utility route.
        $groups = [
            'Admin' => fn ($r) => str_starts_with($r['uri'], '/api/v1/admin'),
            'Restaurant' => fn ($r) => str_starts_with($r['uri'], '/api/v1/restaurant'),
            'Rider' => fn ($r) => str_starts_with($r['uri'], '/api/v1/rider'),
        ];

        $out = MD::fileBanner('`routes/api.php`');
        $out .= "\n".MD::heading('Routes', 1)."\n";
        $out .= "Every application route is defined in `routes/api.php` and auto-prefixed with `/api`. Role-scoped auth groups live under `/api/v1`; the customer group sits at the version root.\n\n";

        $remaining = $routes;

        foreach ($groups as $label => $matcher) {
            $matched = array_values(array_filter($remaining, $matcher));
            $remaining = array_values(array_filter($remaining, fn ($r) => ! $matcher($r)));

            if ($matched === []) {
                continue;
            }

            $out .= MD::heading("{$label} endpoints", 2)."\n";
            $out .= $this->table($matched)."\n";
        }

        $out .= MD::heading('Customer / public', 2)."\n";
        $out .= $this->table($remaining)."\n";

        return $out;
    }

    /**
     * @param  list<array{uri: string, methods: list<string>, name: ?string, controller: ?string, action: ?string, middleware: list<string>, isClosure: bool}>  $routes
     */
    private function table(array $routes): string
    {
        $rows = array_map(function ($route) {
            $handler = $route['isClosure']
                ? 'Closure'
                : class_basename($route['controller']).'@'.$route['action'];

            return [
                implode('|', $route['methods']),
                '`'.$route['uri'].'`',
                $route['name'] ?? '—',
                $handler,
                $route['middleware'] === [] ? '—' : implode(', ', $route['middleware']),
            ];
        }, $routes);

        return MD::table(['Method', 'URI', 'Name', 'Handler', 'Middleware'], $rows);
    }
}
