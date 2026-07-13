<?php

namespace App\Services\Docs\Extractors;

use Illuminate\Routing\Router;

class MiddlewareExtractor
{
    public function __construct(private Router $router) {}

    /**
     * @return list<array{alias: string, class: string}>
     */
    public function extract(): array
    {
        $aliases = [];
        foreach ($this->router->getMiddleware() as $alias => $class) {
            $aliases[] = ['alias' => $alias, 'class' => $class];
        }

        usort($aliases, fn ($a, $b) => $a['alias'] <=> $b['alias']);

        return $aliases;
    }
}
