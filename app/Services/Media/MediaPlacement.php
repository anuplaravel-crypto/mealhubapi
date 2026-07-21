<?php

namespace App\Services\Media;

use App\Http\Resources\Concerns\ResolvesImageUrl;

/**
 * Where a stored image lives, and therefore who may read it.
 *
 * MealHub expressed this as two near-identical services — `Cms\CmsImageService`
 * on the public disk and `Profile\ProfileImageService` on the private one —
 * which drifted apart in format handling and quality for no stated reason. The
 * difference between them is really just three values (disk, path prefix, and
 * how big "large" is), so it collapses into this enum and one
 * {@see ImageUploadService}.
 *
 * This enum is the single source of truth for the storage layout. Both the
 * writer ({@see ImageUploadService}) and the reader
 * ({@see ResolvesImageUrl}) build their paths from {@see self::path()}, so the
 * two cannot disagree about where a file went.
 */
enum MediaPlacement: string
{
    /**
     * Public marketing imagery for the anonymous home page. Linked directly as
     * absolute URLs — a cross-origin SPA should not proxy every logo through
     * PHP. Requires `php artisan storage:link`; without the symlink the files
     * are written but resolve to a 404.
     */
    case Cms = 'cms';

    /**
     * Personal data — profile pictures and restaurant documents. Lives on the
     * private disk and is only ever served through an authenticated controller,
     * so a stored path is not fetchable by URL alone.
     */
    case Personal = 'personal';

    /**
     * The untouched upload, kept alongside the variants so larger sizes can be
     * regenerated later without asking the user to upload again.
     */
    public const ORIGINAL_VARIANT = 'original';

    public const DEFAULT_VARIANT = 'medium';

    public function disk(): string
    {
        return match ($this) {
            self::Cms => 'public',
            self::Personal => 'local',
        };
    }

    public function isPubliclyReadable(): bool
    {
        return $this === self::Cms;
    }

    /**
     * Longest edge, in pixels, for each generated variant.
     *
     * CMS images are display assets on a wide page; personal images are
     * avatars and document scans, so their ceilings are lower.
     *
     * @return array<string, int>
     */
    public function sizes(): array
    {
        return match ($this) {
            self::Cms => ['small' => 400, 'medium' => 800, 'large' => 1600],
            self::Personal => ['small' => 150, 'medium' => 400, 'large' => 800],
        };
    }

    /**
     * Every directory written for one stored image, scaled variants first.
     *
     * @return list<string>
     */
    public function variants(): array
    {
        return [...array_keys($this->sizes()), self::ORIGINAL_VARIANT];
    }

    /**
     * Disk-relative path of one variant: `cms/{collection}/{variant}/{filename}`
     * for CMS imagery, `{collection}/{variant}/{filename}` for personal files —
     * where a personal collection carries the owning role, e.g. `customer/profile`.
     */
    public function path(string $collection, string $variant, string $filename): string
    {
        return implode('/', array_filter([
            $this->prefix(),
            trim($collection, '/'),
            $this->resolveVariant($variant),
            $filename,
        ]));
    }

    /**
     * The requested variant if this placement writes one, `medium` otherwise.
     *
     * Callers pass variants straight from a query string, so an unknown value
     * must degrade to a real image rather than a 404 on a path that was never
     * written.
     */
    public function resolveVariant(?string $variant): string
    {
        return in_array($variant, $this->variants(), true) ? $variant : self::DEFAULT_VARIANT;
    }

    /**
     * Path segment separating this placement's files from the other's on a
     * shared disk. Personal files already namespace themselves by role, so
     * they need none.
     */
    private function prefix(): string
    {
        return match ($this) {
            self::Cms => self::Cms->value,
            self::Personal => '',
        };
    }
}
