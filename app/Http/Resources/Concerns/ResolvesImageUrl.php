<?php

namespace App\Http\Resources\Concerns;

use App\Services\Media\ImageUploadService;
use App\Services\Media\MediaPlacement;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a model's `image` / `image_url` pair into the one absolute URL a
 * cross-origin client can actually load.
 *
 * Per CLAUDE.md this is a Resource concern, not a model one: MealHub's models
 * carried `imageSrc` / `avatarSrc` / `logoUrl` accessors that returned
 * root-relative paths, which resolve against whichever host served the page —
 * fine for Blade, useless to an SPA on another origin.
 *
 * The two columns are a preference, not alternatives: an uploaded file always
 * wins over an externally hosted one. `image_url` exists because the seeded
 * photography is hot-linked and a seeder cannot download it into storage.
 */
trait ResolvesImageUrl
{
    /**
     * Disk, variants and path layout all come from {@see MediaPlacement::Cms} —
     * the same enum {@see ImageUploadService} writes through. Restating them
     * here is how a reader and a writer quietly stop agreeing on where a file
     * is, so this trait deliberately owns none of them.
     *
     * Absolute URL of an uploaded file, falling back to the external link, or
     * null when the record has neither.
     *
     * A null result means "render no image at all" — clients must not emit an
     * empty `src`, which browsers resolve against the current document.
     *
     * @param  string  $collection  the model's IMAGE_COLLECTION
     * @param  string|null  $filename  the stored `image` / `avatar` / `logo` column
     * @param  string|null  $externalUrl  the `image_url` / `avatar_url` column
     */
    protected function resolveImageUrl(string $collection, ?string $filename, ?string $externalUrl = null, ?string $variant = null): ?string
    {
        if ($filename === null || $filename === '') {
            return $externalUrl;
        }

        $placement = MediaPlacement::Cms;

        // Absolute, from the disk's configured url — a relative path cannot
        // address the API host from a client served elsewhere, and would break
        // again the day these images move to S3 or a CDN.
        return Storage::disk($placement->disk())->url(
            $placement->path($collection, $placement->resolveVariant($variant), $filename)
        );
    }
}
