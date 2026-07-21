<?php

namespace App\Services\Media;

use App\Http\Requests\Concerns\ValidatesUploadedImage;
use App\Http\Resources\Concerns\ResolvesImageUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * The one place an uploaded image is written, replaced, or removed.
 *
 * Every upload becomes four files — the untouched original plus three scaled
 * variants — under the layout {@see MediaPlacement::path()} defines, on the
 * disk that placement names. Phases 5, 8, 9 and 10 all upload images; this
 * exists so they do not each grow their own copy of the resize-and-clean-up
 * dance, which is what happened in MealHub.
 *
 * What this class does *not* do is decide whether a file is acceptable — size
 * and format are validation, and live in {@see ValidatesUploadedImage} so the
 * client gets a 422 with field-level errors rather than an exception from GD.
 * Callers must therefore only reach here with an already-validated upload.
 */
class ImageUploadService
{
    /**
     * Source extensions re-encoded to themselves rather than to JPEG.
     *
     * Flattening a transparent PNG or WebP onto JPEG's opaque canvas turns the
     * transparency black, which on a white navbar reads as a solid box where
     * the logo should be.
     *
     * @var array<string, string>
     */
    private const LOSSLESS_FORMATS = ['png' => 'png', 'webp' => 'webp'];

    private const ORIGINAL_QUALITY = 90;

    private const VARIANT_QUALITY = 85;

    /**
     * Stored filenames are random rather than derived from the upload, so a
     * name leaks nothing about its owner and a private file cannot be guessed.
     */
    private const FILENAME_LENGTH = 40;

    /**
     * Write an upload as the original plus every scaled variant, and return the
     * stored filename for the caller to persist on its model.
     *
     * Pass the filename being replaced to have its variants removed once the
     * new ones are safely written — an unreferenced file is otherwise
     * unreachable and never reclaimed. The order matters: a failure while
     * encoding must not leave the record pointing at a deleted image.
     */
    public function store(MediaPlacement $placement, string $collection, UploadedFile $file, ?string $replacing = null): string
    {
        $format = $this->formatFor($file);
        $filename = Str::random(self::FILENAME_LENGTH).'.'.$format;
        $manager = ImageManager::gd();
        $disk = Storage::disk($placement->disk());

        $disk->put(
            $placement->path($collection, MediaPlacement::ORIGINAL_VARIANT, $filename),
            $this->encode($manager->read($file->getRealPath()), $format, self::ORIGINAL_QUALITY)
        );

        foreach ($placement->sizes() as $variant => $longestEdge) {
            // Re-read the source per variant: modifiers mutate the instance, so
            // reusing one would shrink each size relative to the previous.
            $image = $manager->read($file->getRealPath())->scaleDown($longestEdge, $longestEdge);

            $disk->put(
                $placement->path($collection, $variant, $filename),
                $this->encode($image, $format, self::VARIANT_QUALITY)
            );
        }

        $this->delete($placement, $collection, $replacing);

        return $filename;
    }

    /**
     * Remove every variant of a stored image. A null filename is a no-op, so
     * callers can pass a column that may never have been set.
     */
    public function delete(MediaPlacement $placement, string $collection, ?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }

        $disk = Storage::disk($placement->disk());

        foreach ($placement->variants() as $variant) {
            $disk->delete($placement->path($collection, $variant, $filename));
        }
    }

    /**
     * Disk-relative path of a stored variant, or null when the record has no
     * image or the file is missing.
     *
     * This is the private counterpart to
     * {@see ResolvesImageUrl}: personal files have
     * no public URL, so a controller streams them from this path after checking
     * the caller may see them. Existence is verified here because a null lets
     * that controller answer 404 instead of streaming an empty body.
     */
    public function pathFor(MediaPlacement $placement, string $collection, ?string $filename, ?string $variant = null): ?string
    {
        if ($filename === null || $filename === '') {
            return null;
        }

        $path = $placement->path($collection, $placement->resolveVariant($variant), $filename);

        return Storage::disk($placement->disk())->exists($path) ? $path : null;
    }

    /**
     * Output format for an upload: transparency-capable sources keep their own
     * format, everything else becomes JPEG.
     *
     * MealHub applied this to CMS images only and forced profile pictures to
     * JPEG, so a PNG avatar with a transparent background gained a black one.
     * The rule is the same either way, so it is applied to both here.
     */
    private function formatFor(UploadedFile $file): string
    {
        $extension = Str::lower((string) $file->getClientOriginalExtension());

        return self::LOSSLESS_FORMATS[$extension] ?? 'jpg';
    }

    /**
     * Encode an image to the given format. PNG ignores the quality argument —
     * it is lossless, and Intervention's PNG encoder takes no quality.
     */
    private function encode(ImageInterface $image, string $format, int $quality): string
    {
        return match ($format) {
            'png' => $image->toPng()->toString(),
            'webp' => $image->toWebp($quality)->toString(),
            default => $image->toJpeg($quality)->toString(),
        };
    }
}
