<?php

namespace App\Http\Resources\Concerns;

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
     * Disk holding CMS imagery. Public marketing assets on an anonymous page,
     * so they are linked directly rather than streamed through a controller —
     * the opposite of the private documents in Phase 9.
     */
    private const IMAGE_DISK = 'public';

    /**
     * Longest-edge variants the upload service writes per stored image.
     *
     * This is the storage-layout contract the models' `IMAGE_COLLECTION`
     * constants complete: `cms/{collection}/{variant}/{filename}`. Phase 4's
     * ImageUploadService must write to this same layout — nothing enforces it
     * across the two classes but this comment.
     *
     * @var list<string>
     */
    private const IMAGE_VARIANTS = ['small', 'medium', 'large', 'original'];

    private const DEFAULT_VARIANT = 'medium';

    /**
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
    protected function resolveImageUrl(string $collection, ?string $filename, ?string $externalUrl = null, string $variant = self::DEFAULT_VARIANT): ?string
    {
        if ($filename === null || $filename === '') {
            return $externalUrl;
        }

        $directory = in_array($variant, self::IMAGE_VARIANTS, true) ? $variant : self::DEFAULT_VARIANT;

        // Absolute, from the disk's configured url — a relative path cannot
        // address the API host from a client served elsewhere, and would break
        // again the day these images move to S3 or a CDN.
        return Storage::disk(self::IMAGE_DISK)->url("cms/{$collection}/{$directory}/{$filename}");
    }
}
