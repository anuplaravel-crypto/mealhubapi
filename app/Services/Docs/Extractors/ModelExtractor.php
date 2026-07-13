<?php

namespace App\Services\Docs\Extractors;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\Finder\Finder;

class ModelExtractor
{
    public function __construct(private RouteExtractor $routes) {}

    /**
     * @return list<array{
     *     class: string,
     *     table: ?string,
     *     fillable: list<string>,
     *     hidden: list<string>,
     *     casts: array<string, string>,
     *     columns: list<array{name: string, type: string, nullable: bool}>,
     *     relationships: list<array{method: string, type: string, related: string}>,
     *     referencedByControllers: bool,
     * }>
     */
    public function extract(): array
    {
        $modelsPath = app_path('Models');
        if (! is_dir($modelsPath)) {
            return [];
        }

        $referencedModels = $this->modelsReferencedByRoutedControllers();

        $models = [];
        foreach (Finder::create()->files()->name('*.php')->in($modelsPath) as $file) {
            $class = 'App\\Models\\'.$file->getBasename('.php');
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $instance = new $class;
            $reflection = new ReflectionClass($class);

            $models[] = [
                'class' => $class,
                'table' => $this->safeTable($instance),
                'fillable' => $instance->getFillable(),
                'hidden' => $instance->getHidden(),
                'casts' => $instance->getCasts(),
                'columns' => $this->columns($instance),
                'relationships' => $this->relationships($reflection, $instance),
                'referencedByControllers' => in_array($class, $referencedModels, true),
            ];
        }

        usort($models, fn ($a, $b) => $a['class'] <=> $b['class']);

        return $models;
    }

    private function safeTable(Model $instance): ?string
    {
        try {
            return $instance->getTable();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{name: string, type: string, nullable: bool}>
     */
    private function columns(Model $instance): array
    {
        $table = $this->safeTable($instance);
        if (! $table) {
            return [];
        }

        try {
            if (! Schema::hasTable($table)) {
                return [];
            }

            return array_map(fn ($column) => [
                'name' => $column['name'],
                'type' => $column['type'] ?? ($column['type_name'] ?? 'unknown'),
                'nullable' => (bool) ($column['nullable'] ?? false),
            ], Schema::getColumns($table));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Detect relationship methods by their return-type hint
     * (BelongsTo/HasMany/BelongsToMany/HasOne/...).
     *
     * @return list<array{method: string, type: string, related: string}>
     */
    private function relationships(ReflectionClass $reflection, Model $instance): array
    {
        $relationships = [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfParameters() > 0 || $method->isStatic() || $method->class !== $reflection->getName()) {
                continue;
            }

            $returnType = $method->getReturnType();
            if (! $returnType instanceof ReflectionNamedType) {
                continue;
            }

            $typeName = $returnType->getName();
            if (! is_subclass_of($typeName, Relation::class) && $typeName !== Relation::class) {
                continue;
            }

            $related = '?';
            try {
                $relation = $instance->{$method->getName()}();
                $related = get_class($relation->getRelated());
            } catch (\Throwable) {
                // Best effort — some relations may require DB state to resolve.
            }

            $relationships[] = [
                'method' => $method->getName(),
                'type' => class_basename($typeName),
                'related' => $related,
            ];
        }

        return $relationships;
    }

    /**
     * Which App\Models\* classes are `use`d by at least one controller that
     * is actually reachable via a route.
     *
     * @return list<string>
     */
    private function modelsReferencedByRoutedControllers(): array
    {
        $controllers = array_unique(array_column($this->routes->uniqueControllerActions(), 'controller'));
        $referenced = [];

        foreach ($controllers as $controllerClass) {
            if (! class_exists($controllerClass)) {
                continue;
            }

            $file = (new ReflectionClass($controllerClass))->getFileName();
            if (! $file || ! is_readable($file)) {
                continue;
            }

            $source = file_get_contents($file);
            if (preg_match_all('/App\\\\Models\\\\([A-Za-z0-9_]+)/', $source, $matches)) {
                foreach ($matches[1] as $modelName) {
                    $referenced[] = 'App\\Models\\'.$modelName;
                }
            }
        }

        return array_unique($referenced);
    }
}
