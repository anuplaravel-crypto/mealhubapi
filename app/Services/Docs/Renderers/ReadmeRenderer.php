<?php

namespace App\Services\Docs\Renderers;

use App\Services\Docs\Support\MarkdownBuilder as MD;

class ReadmeRenderer
{
    /**
     * @param  array{routes: int, controllers: int, models: int}  $counts
     * @param  list<string>  $featureDocs  basenames of docs/features/*.md (hand-written, not generated)
     */
    public function render(array $counts, array $featureDocs = []): string
    {
        $appName = config('app.name', 'Laravel');

        $out = MD::fileBanner('the linked files below');
        $out .= "\n".MD::heading("{$appName} — Documentation", 1)."\n\n";
        $out .= "The reference files below are **auto-generated** from the live code. Regenerate them with:\n\n";
        $out .= MD::codeBlock('php artisan docs:generate', 'bash')."\n";

        $out .= "## Reference (auto-generated)\n\n";
        $out .= MD::bullets([
            '[architecture.md](architecture.md) — module structure, authentication design, user roles, migrations timeline, automated observations',
            "[models.md](models.md) — every Eloquent model ({$counts['models']} found): live schema columns, fillable/hidden/casts, relationships",
            "[controllers.md](controllers.md) — every routed controller ({$counts['controllers']} found): method signatures, Form Request validation rules, extracted response examples",
            "[routes.md](routes.md) — the full route table ({$counts['routes']} routes), grouped by role",
        ])."\n";

        if ($featureDocs !== []) {
            sort($featureDocs);
            $out .= "## Feature docs (hand-written)\n\n";
            $out .= "One per domain concern under `docs/features/`. These are **not** auto-generated — they carry the \"why\" and business rules the code can't introspect.\n\n";
            $out .= MD::bullets(array_map(
                fn ($file) => '[features/'.$file.'](features/'.$file.')',
                $featureDocs
            ))."\n";
        }

        return $out;
    }
}
