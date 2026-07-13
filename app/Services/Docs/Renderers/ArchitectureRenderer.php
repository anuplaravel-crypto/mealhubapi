<?php

namespace App\Services\Docs\Renderers;

use App\Services\Docs\Support\MarkdownBuilder as MD;
use Symfony\Component\Finder\Finder;

class ArchitectureRenderer
{
    /**
     * @param  list<array{uri: string, methods: list<string>, name: ?string, controller: ?string, action: ?string, middleware: list<string>, isClosure: bool}>  $routes
     * @param  list<array{class: string, table: ?string, fillable: list<string>, hidden: list<string>, casts: array<string, string>, columns: list<array{name: string, type: string, nullable: bool}>, relationships: list<array{method: string, type: string, related: string}>, referencedByControllers: bool}>  $models
     * @param  list<array{alias: string, class: string}>  $middleware
     */
    public function render(array $routes, array $models, array $middleware): string
    {
        $out = MD::fileBanner('[routes.md](routes.md), [controllers.md](controllers.md), and [models.md](models.md) for the underlying data this page summarizes');
        $out .= "\n".MD::heading('Architecture', 1)."\n\n";

        $out .= $this->modulesSection($routes);
        $out .= $this->authSection($middleware);
        $out .= $this->rolesSection($models);
        $out .= $this->migrationsSection();
        $out .= $this->observationsSection($routes, $models);

        return $out;
    }

    /**
     * @param  list<array{uri: string, methods: list<string>, name: ?string, controller: ?string, action: ?string, middleware: list<string>, isClosure: bool}>  $routes
     */
    private function modulesSection(array $routes): string
    {
        // Collect the domain sub-namespace under Api\{Version}\ for every
        // routed controller, e.g. App\Http\Controllers\Api\V1\Auth\... → "Api/V1/Auth".
        $groups = [];
        foreach ($routes as $route) {
            if ($route['controller'] === null || ! str_starts_with($route['controller'], 'App\\')) {
                continue;
            }

            $parts = explode('\\', $route['controller']);
            $apiIndex = array_search('Api', $parts, true);

            if ($apiIndex !== false) {
                // Everything from Api up to (but not including) the controller class.
                $segments = array_slice($parts, $apiIndex, -1);
                $groups[implode('/', $segments)] = true;
            } else {
                // Non-versioned controller — group by the segment after Controllers.
                $controllersIndex = array_search('Controllers', $parts, true);
                $area = $controllersIndex !== false ? ($parts[$controllersIndex + 1] ?? 'App') : 'App';
                $groups[$area] = true;
            }
        }

        $out = MD::heading('Module structure', 2)."\n";
        $out .= "This is a stateless JSON REST API (no Blade views of its own). Controllers are **thin** — they validate via a Form Request, delegate to a service in `app/Services/`, and shape the response through an API Resource + the `App\\Http\\Traits\\ApiResponse` envelope. API controllers are versioned and grouped by domain under `app/Http/Controllers/`:\n\n";

        $labels = array_keys($groups);
        sort($labels);
        $out .= MD::bullets(array_map(fn ($g) => "`{$g}/`", $labels))."\n";

        return $out;
    }

    /**
     * @param  list<array{alias: string, class: string}>  $middleware
     */
    private function authSection(array $middleware): string
    {
        $out = MD::heading('Authentication', 2)."\n";
        $out .= "API authentication uses **Laravel Sanctum personal access tokens** — not JWT/cookies, and not Laravel's session guard. ";
        $out .= 'The four roles (customer, admin, restaurant, rider) share one `users` table and each has its own registration/verify-otp/login/forgot-password/reset-password/logout endpoints; `AuthService` scopes every lookup by role, so a token or credentials for one role are never valid at another role\'s endpoints. ';
        $out .= "Registration and password reset are OTP-based (a 6-digit code emailed via `OtpNotification`).\n\n";
        $out .= "Protected routes use the `auth:sanctum` middleware; tokens are issued with `createToken()` and revoked on logout / password reset. See [features/authentication.md](features/authentication.md) for the full flow.\n\n";

        if ($middleware !== []) {
            $out .= "**All registered middleware aliases**\n\n";
            $rows = array_map(fn ($m) => ['`'.$m['alias'].'`', '`'.$m['class'].'`'], $middleware);
            $out .= MD::table(['Alias', 'Class'], $rows)."\n";
        }

        return $out;
    }

    /**
     * @param  list<array{class: string, table: ?string, fillable: list<string>, hidden: list<string>, casts: array<string, string>, columns: list<array{name: string, type: string, nullable: bool}>, relationships: list<array{method: string, type: string, related: string}>, referencedByControllers: bool}>  $models
     */
    private function rolesSection(array $models): string
    {
        $out = MD::heading('User roles', 2)."\n";

        $userModel = array_values(array_filter($models, fn ($m) => class_basename($m['class']) === 'User'));
        $roleColumn = null;
        if ($userModel !== []) {
            foreach ($userModel[0]['columns'] as $column) {
                if ($column['name'] === 'role') {
                    $roleColumn = $column;
                    break;
                }
            }
        }

        if ($roleColumn && preg_match_all("/'([a-z_]+)'/", $roleColumn['type'], $matches)) {
            $out .= "The `users.role` column (`{$roleColumn['type']}`) discriminates all four roles from a single table:\n\n";
            $out .= MD::bullets(array_map(fn ($r) => "`{$r}`", $matches[1]))."\n";
        } else {
            $out .= MD::note('Could not read the `role` enum values from the live schema — see [models.md](models.md) for the User model.')."\n";
        }

        return $out;
    }

    private function migrationsSection(): string
    {
        $path = database_path('migrations');
        if (! is_dir($path)) {
            return '';
        }

        $files = [];
        foreach (Finder::create()->files()->name('*.php')->in($path)->sortByName() as $file) {
            $files[] = $file->getBasename('.php');
        }

        $out = MD::heading('Migrations timeline', 2)."\n";
        $out .= MD::bullets(array_map(fn ($f) => "`{$f}`", $files))."\n";

        return $out;
    }

    /**
     * Best-effort, automatically-derived notes — not hardcoded per package.
     *
     * @param  list<array{uri: string, methods: list<string>, name: ?string, controller: ?string, action: ?string, middleware: list<string>, isClosure: bool}>  $routes
     * @param  list<array{class: string, table: ?string, fillable: list<string>, hidden: list<string>, casts: array<string, string>, columns: list<array{name: string, type: string, nullable: bool}>, relationships: list<array{method: string, type: string, related: string}>, referencedByControllers: bool}>  $models
     */
    private function observationsSection(array $routes, array $models): string
    {
        $notes = [];

        $unnamed = array_filter($routes, fn ($r) => ! $r['isClosure'] && $r['name'] === null);
        if ($unnamed !== []) {
            $notes[] = count($unnamed).' route(s) have no `->name()` — see [routes.md](routes.md) ("—" in the Name column).';
        }

        foreach ($models as $model) {
            if (! $model['referencedByControllers']) {
                $notes[] = '`'.class_basename($model['class']).'` model exists but is not referenced by any routed controller.';
            }
        }

        $unusedDeps = $this->unusedComposerDependencies();
        foreach ($unusedDeps as $dep) {
            $notes[] = "Composer package `{$dep}` is installed but no matching reference was found under `app/` (best-effort keyword scan — may miss indirect usage).";
        }

        if ($notes === []) {
            return '';
        }

        $out = MD::heading('Observations', 2)."\n";
        $out .= MD::bullets($notes)."\n";

        return $out;
    }

    /**
     * Best-effort: for each non-framework composer dependency, derive a
     * keyword from its package name and grep app/ for it. Not a precise
     * static-analysis tool — flags candidates for a human to double check.
     *
     * @return list<string>
     */
    private function unusedComposerDependencies(): array
    {
        $composerJson = base_path('composer.json');
        if (! is_readable($composerJson)) {
            return [];
        }

        $data = json_decode(file_get_contents($composerJson), true);
        $require = $data['require'] ?? [];
        unset($require['php'], $require['laravel/framework']);

        $appPath = app_path();
        $appSource = '';
        foreach (Finder::create()->files()->name('*.php')->in($appPath) as $file) {
            $appSource .= $file->getContents();
        }

        $unused = [];
        foreach (array_keys($require) as $package) {
            $name = explode('/', $package)[1] ?? $package;
            $keyword = preg_replace('/^(laravel-|php-)|(-php|\.php)$/', '', $name);
            $keyword = str_replace('-', '', $keyword);

            if ($keyword === '' || stripos($appSource, $keyword) !== false) {
                continue;
            }

            $unused[] = $package;
        }

        return $unused;
    }
}
