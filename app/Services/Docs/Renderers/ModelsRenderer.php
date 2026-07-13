<?php

namespace App\Services\Docs\Renderers;

use App\Services\Docs\Support\MarkdownBuilder as MD;

class ModelsRenderer
{
    /**
     * @param  list<array{class: string, table: ?string, fillable: list<string>, hidden: list<string>, casts: array<string, string>, columns: list<array{name: string, type: string, nullable: bool}>, relationships: list<array{method: string, type: string, related: string}>, referencedByControllers: bool}>  $models
     */
    public function render(array $models): string
    {
        $out = MD::fileBanner('`app/Models/**`');
        $out .= "\n".MD::heading('Models', 1)."\n\n";

        foreach ($models as $model) {
            $out .= MD::heading(class_basename($model['class']), 2)."\n";
            $out .= '**File:** `'.str_replace('\\', '/', str_replace('App\\', 'app/', $model['class'])).".php`  \n";
            $out .= '**Table:** `'.($model['table'] ?? 'unknown')."`\n\n";

            if (! $model['referencedByControllers']) {
                $out .= MD::note('Not referenced by any routed controller found — this model has no active read/write path today.')."\n";
            }

            if ($model['columns'] !== []) {
                $out .= "**Columns** (live schema)\n\n";
                $rows = array_map(fn ($c) => [$c['name'], $c['type'], $c['nullable'] ? 'yes' : 'no'], $model['columns']);
                $out .= MD::table(['Column', 'Type', 'Nullable'], $rows)."\n";
            }

            if ($model['fillable'] !== []) {
                $out .= '**Fillable:** '.implode(', ', array_map(fn ($f) => "`{$f}`", $model['fillable']))."\n\n";
            }

            if ($model['hidden'] !== []) {
                $out .= '**Hidden:** '.implode(', ', array_map(fn ($f) => "`{$f}`", $model['hidden']))."\n\n";
            }

            if ($model['casts'] !== []) {
                $out .= "**Casts**\n\n";
                $rows = [];
                foreach ($model['casts'] as $field => $cast) {
                    $rows[] = [$field, $cast];
                }
                $out .= MD::table(['Field', 'Cast'], $rows)."\n";
            }

            if ($model['relationships'] !== []) {
                $out .= "**Relationships**\n\n";
                $rows = array_map(fn ($r) => [
                    $r['method'].'()',
                    $r['type'],
                    $r['related'] !== '?' ? class_basename($r['related']) : '?',
                ], $model['relationships']);
                $out .= MD::table(['Method', 'Type', 'Related model'], $rows)."\n";
            }

            $out .= "---\n\n";
        }

        return rtrim($out)."\n";
    }
}
