<?php

namespace App\Services\Media;

use App\Http\Resources\Concerns\ResolvesImageUrl;
use Illuminate\Support\Str;

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
     * Personal data — profile pictures and rider vehicle photos. Lives on the
     * private disk and is only ever served through an authenticated controller,
     * so a stored path is not fetchable by URL alone.
     */
    case Personal = 'personal';

    /**
     * Identity paperwork — a restaurant's business licence and photo ID.
     *
     * Private like {@see self::Personal}, and separate from it for two reasons:
     * a document is read, not glanced at, so its ceilings are higher than an
     * avatar's; and it is the one placement that accepts a file this service
     * cannot rasterise, which {@see self::PASSTHROUGH_EXTENSIONS} handles.
     */
    case Document = 'document';

    /**
     * The untouched upload, kept alongside the variants so larger sizes can be
     * regenerated later without asking the user to upload again.
     */
    public const ORIGINAL_VARIANT = 'original';

    public const DEFAULT_VARIANT = 'medium';

    /**
     * Extensions stored exactly as uploaded, with no scaled variants.
     *
     * A PDF cannot be scaled by an image encoder, and a licence is commonly
     * filed as one. Rejecting the format would have been the tidier code and
     * the worse product; storing it under {@see self::ORIGINAL_VARIANT} and
     * resolving every read of it back to that directory is the whole
     * accommodation.
     *
     * @var list<string>
     */
    public const PASSTHROUGH_EXTENSIONS = ['pdf'];

    public function disk(): string
    {
        return match ($this) {
            self::Cms => 'public',
            self::Personal, self::Document => 'local',
        };
    }

    public function isPubliclyReadable(): bool
    {
        return $this === self::Cms;
    }

    /**
     * Longest edge, in pixels, for each generated variant.
     *
     * CMS images are display assets on a wide page; personal images are avatars
     * shown at thumbnail sizes. Documents sit between them — an admin has to
     * *read* a licence number off one, so `large` matches the CMS ceiling even
     * though the file is private.
     *
     * @return array<string, int>
     */
    public function sizes(): array
    {
        return match ($this) {
            self::Cms => ['small' => 400, 'medium' => 800, 'large' => 1600],
            self::Personal => ['small' => 150, 'medium' => 400, 'large' => 800],
            self::Document => ['small' => 300, 'medium' => 800, 'large' => 1600],
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
        return $this->directory($collection, $variant).'/'.$filename;
    }

    /**
     * The directory one variant of a collection is written to — the same path
     * as {@see self::path()} without the file, for the writer that hands a
     * destination and a name to the filesystem separately.
     */
    public function directory(string $collection, string $variant): string
    {
        return implode('/', array_filter([
            $this->prefix(),
            trim($collection, '/'),
            $this->resolveVariant($variant),
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
     * The variant a *particular stored file* lives under.
     *
     * Identical to {@see self::resolveVariant()} for anything scalable, but a
     * pass-through file has only the original — asking it for `small` must
     * resolve to the one directory it was written to rather than 404 on a path
     * that could never have existed. Both the writer and the reader ask this,
     * so neither can invent a directory the other did not use.
     */
    public function variantFor(?string $filename, ?string $requested): string
    {
        return self::isPassthrough($filename)
            ? self::ORIGINAL_VARIANT
            : $this->resolveVariant($requested);
    }

    /**
     * Whether a filename names a format stored as uploaded rather than
     * re-encoded into variants.
     *
     * Keyed on the stored extension rather than on the placement: the file
     * itself is what decides, and a document collection legitimately holds a
     * mix of scanned images and PDFs.
     */
    public static function isPassthrough(?string $filename): bool
    {
        if ($filename === null || $filename === '') {
            return false;
        }

        return in_array(
            Str::lower(pathinfo($filename, PATHINFO_EXTENSION)),
            self::PASSTHROUGH_EXTENSIONS,
            true,
        );
    }

    /**
     * Path segment separating this placement's files from the others' on a
     * shared disk. Private files already namespace themselves by role, so they
     * need none.
     */
    private function prefix(): string
    {
        return match ($this) {
            self::Cms => self::Cms->value,
            self::Personal, self::Document => '',
        };
    }
}
