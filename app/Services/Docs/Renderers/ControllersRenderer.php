<?php

namespace App\Services\Docs\Renderers;

use App\Services\Docs\Support\MarkdownBuilder as MD;

class ControllersRenderer
{
    /**
     * @param  array<string, list<array{method: string, signature: string, returnType: ?string, summary: ?string, requestBody: list<array{field: string, rules: string}>, responses: list<array{status: string, body: string}>}>>  $controllers
     */
    public function render(array $controllers): string
    {
        $out = MD::fileBanner('`app/Http/Controllers/**`');
        $out .= "\n".MD::heading('Controllers', 1)."\n";
        $out .= "Only controllers reachable from a registered route are documented here — see [routes.md](routes.md) for the full route table.\n\n";

        foreach ($controllers as $class => $methods) {
            $out .= MD::heading($this->shortName($class), 2)."\n";
            $out .= '**File:** `'.$this->classToPath($class)."`\n\n";

            foreach ($methods as $method) {
                $out .= $this->renderMethod($method);
            }

            $out .= "---\n\n";
        }

        return rtrim($out)."\n";
    }

    /**
     * @param  array{method: string, signature: string, returnType: ?string, summary: ?string, requestBody: list<array{field: string, rules: string}>, responses: list<array{status: string, body: string}>}  $method
     */
    private function renderMethod(array $method): string
    {
        $returnType = $method['returnType'] ? ": {$method['returnType']}" : '';
        $out = MD::heading("`{$method['method']}{$method['signature']}{$returnType}`", 3)."\n";

        if ($method['summary']) {
            $out .= $method['summary']."\n\n";
        }

        if ($method['requestBody'] !== []) {
            $out .= "**Request body**\n\n";
            $rows = array_map(fn ($f) => [$f['field'], '`'.$f['rules'].'`'], $method['requestBody']);
            $out .= MD::table(['Field', 'Rules'], $rows)."\n";
        }

        foreach ($method['responses'] as $response) {
            $out .= "**Response — {$response['status']}**\n\n";
            $out .= MD::codeBlock($response['body'], 'jsonc')."\n";
        }

        return $out."\n";
    }

    private function shortName(string $class): string
    {
        return str_replace('App\\Http\\Controllers\\', '', $class);
    }

    private function classToPath(string $class): string
    {
        return str_replace('\\', '/', str_replace('App\\', 'app/', $class)).'.php';
    }
}
