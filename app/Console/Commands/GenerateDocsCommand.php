<?php

namespace App\Console\Commands;

use App\Services\Docs\Extractors\ControllerExtractor;
use App\Services\Docs\Extractors\MiddlewareExtractor;
use App\Services\Docs\Extractors\ModelExtractor;
use App\Services\Docs\Extractors\RouteExtractor;
use App\Services\Docs\Renderers\ArchitectureRenderer;
use App\Services\Docs\Renderers\ControllersRenderer;
use App\Services\Docs\Renderers\ModelsRenderer;
use App\Services\Docs\Renderers\ReadmeRenderer;
use App\Services\Docs\Renderers\RoutesRenderer;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class GenerateDocsCommand extends Command
{
    protected $signature = 'docs:generate
        {--check : Verify docs/*.md is up to date without writing; exits non-zero if stale}
        {--path= : Output directory for the generated docs (default: docs). Mainly for tests.}';

    protected $description = 'Regenerate the reference docs (docs/*.md) by introspecting live routes, controllers, models, and the DB schema';

    public function handle(
        RouteExtractor $routeExtractor,
        ControllerExtractor $controllerExtractor,
        ModelExtractor $modelExtractor,
        MiddlewareExtractor $middlewareExtractor,
        ReadmeRenderer $readmeRenderer,
        RoutesRenderer $routesRenderer,
        ControllersRenderer $controllersRenderer,
        ModelsRenderer $modelsRenderer,
        ArchitectureRenderer $architectureRenderer,
    ): int {
        $routes = $routeExtractor->extract();
        $controllers = $controllerExtractor->extract();
        $models = $modelExtractor->extract();
        $middleware = $middlewareExtractor->extract();

        $docsPath = $this->option('path') ?: base_path('docs');

        $files = [
            'controllers.md' => $controllersRenderer->render($controllers),
            'models.md' => $modelsRenderer->render($models),
            'routes.md' => $routesRenderer->render($routes),
            'architecture.md' => $architectureRenderer->render($routes, $models, $middleware),
        ];

        $files['README.md'] = $readmeRenderer->render([
            'routes' => count($routes),
            'controllers' => count($controllers),
            'models' => count($models),
        ], $this->featureDocs($docsPath));

        if ($this->option('check')) {
            return $this->check($docsPath, $files);
        }

        if (! is_dir($docsPath)) {
            mkdir($docsPath, 0755, true);
        }

        foreach ($files as $name => $content) {
            file_put_contents($docsPath.'/'.$name, $content);
            $this->line("Wrote {$docsPath}/{$name}");
        }

        $this->info('Documentation regenerated.');

        return self::SUCCESS;
    }

    /**
     * Basenames of the hand-written docs/features/*.md, so the generated
     * README can keep linking them (the generator never writes these).
     *
     * @return list<string>
     */
    private function featureDocs(string $docsPath): array
    {
        $featuresPath = $docsPath.'/features';
        if (! is_dir($featuresPath)) {
            return [];
        }

        $files = [];
        foreach (Finder::create()->files()->name('*.md')->in($featuresPath)->sortByName() as $file) {
            $files[] = $file->getBasename();
        }

        return $files;
    }

    /**
     * @param  array<string, string>  $files
     */
    private function check(string $docsPath, array $files): int
    {
        $stale = [];

        foreach ($files as $name => $content) {
            $existing = is_readable($docsPath.'/'.$name) ? file_get_contents($docsPath.'/'.$name) : null;
            if ($existing !== $content) {
                $stale[] = $name;
            }
        }

        if ($stale === []) {
            $this->info('docs/ is up to date.');

            return self::SUCCESS;
        }

        $this->error('docs/ is stale. Run `php artisan docs:generate` to refresh:');
        foreach ($stale as $name) {
            $this->line("  - {$name}");
        }

        return self::FAILURE;
    }
}
