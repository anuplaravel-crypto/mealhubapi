<?php

namespace App\Http\Requests\Concerns;

/**
 * The one definition of what counts as an acceptable image upload.
 *
 * MealHub carried two copies of this — `Cms\Concerns\ValidatesCmsImage` and
 * `Profile\Concerns\ValidatesProfilePicture` — whose rules had already
 * converged on identical formats and an identical ceiling, leaving only the
 * error wording different. That is a parameter, not a second trait, so the two
 * are merged here and the wording is passed in.
 *
 * Requests that accept an image use these rules rather than restating them, so
 * raising the ceiling or accepting a new format is one edit rather than eight.
 */
trait ValidatesUploadedImage
{
    /**
     * SVG is deliberately absent. It is an XSS vector when served from a
     * public disk, and Intervention's GD driver cannot rasterise it into the
     * scaled variants every other format gets.
     *
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Largest accepted upload, in kilobytes. Must stay under PHP's own
     * `upload_max_filesize` — past that the request arrives with an empty file
     * bag and the rules below report "required" rather than "too large".
     */
    public const MAX_KILOBYTES = 2048;

    /**
     * @return list<string>
     */
    protected function uploadedImageRules(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'image',
            'mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
            'max:'.self::MAX_KILOBYTES,
        ];
    }

    /**
     * Field-level messages naming the upload the way the client's form does,
     * e.g. `uploadedImageMessages('logo', 'The site logo')`.
     *
     * @return array<string, string>
     */
    protected function uploadedImageMessages(string $field, string $label): array
    {
        return [
            $field.'.required' => $label.' is required.',
            $field.'.image' => $label.' must be an image.',
            $field.'.mimes' => $label.' must be a '.implode(', ', self::ALLOWED_EXTENSIONS).' file.',
            $field.'.max' => $label.' may not be larger than '.(self::MAX_KILOBYTES / 1024).' MB.',
        ];
    }
}
