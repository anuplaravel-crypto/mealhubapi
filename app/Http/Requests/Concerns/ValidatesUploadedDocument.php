<?php

namespace App\Http\Requests\Concerns;

use App\Services\Media\MediaPlacement;

/**
 * What counts as an acceptable *document* upload — identity paperwork, not
 * display imagery.
 *
 * A deliberate second concern rather than more methods on
 * {@see ValidatesUploadedImage}, because a document is a different class of
 * file with different answers: a business licence is very often a PDF, and a
 * scan people can read needs more headroom than an avatar. The rule that matters
 * — one place per file class, never a rule restated in a Form Request — still
 * holds: nothing here duplicates the image trait, and raising the ceiling or
 * accepting a format stays one edit.
 */
trait ValidatesUploadedDocument
{
    /**
     * PDF is accepted here and nowhere else. It cannot be rasterised into
     * variants, so it is stored as uploaded — see
     * {@see MediaPlacement::PASSTHROUGH_EXTENSIONS}. SVG
     * stays absent for the same reason it is absent from image uploads.
     *
     * @var list<string>
     */
    public const ALLOWED_DOCUMENT_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    /**
     * Largest accepted document, in kilobytes — double the image ceiling, since
     * a legible multi-page scan is simply bigger than an avatar. Must stay under
     * PHP's own `upload_max_filesize`, past which the request arrives with an
     * empty file bag and the rules below report "required" rather than
     * "too large".
     */
    public const MAX_DOCUMENT_KILOBYTES = 4096;

    /**
     * `file` rather than `image`: the `image` rule rejects a PDF outright, and
     * the format list below is what actually constrains the upload.
     *
     * @return list<string>
     */
    protected function uploadedDocumentRules(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:'.implode(',', self::ALLOWED_DOCUMENT_EXTENSIONS),
            'max:'.self::MAX_DOCUMENT_KILOBYTES,
        ];
    }

    /**
     * Field-level messages naming the document the way the client's form does,
     * e.g. `uploadedDocumentMessages('doc_image1', 'The business licence')`.
     *
     * @return array<string, string>
     */
    protected function uploadedDocumentMessages(string $field, string $label): array
    {
        return [
            $field.'.required' => $label.' is required.',
            $field.'.file' => $label.' must be an uploaded file.',
            $field.'.mimes' => $label.' must be a '.implode(', ', self::ALLOWED_DOCUMENT_EXTENSIONS).' file.',
            $field.'.max' => $label.' may not be larger than '.(self::MAX_DOCUMENT_KILOBYTES / 1024).' MB.',
        ];
    }
}
