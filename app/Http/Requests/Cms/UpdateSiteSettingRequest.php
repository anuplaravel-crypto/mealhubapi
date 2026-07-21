<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\Concerns\ValidatesUploadedImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The site-wide branding payload.
 *
 * There is no create or delete counterpart: the table is a singleton, so this
 * is the only write it accepts. The reference app's `failedValidation()`
 * override is not ported — the handler in `bootstrap/app.php` already shapes
 * every 422 into the project envelope.
 */
class UpdateSiteSettingRequest extends FormRequest
{
    use ValidatesUploadedImage;

    /**
     * The route carries `auth:sanctum` + `role:admin`, and the payload names no
     * row — there is exactly one — so there is no ownership to authorize.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The two required fields are the ones a client cannot render the header
     * without: a site name for the document title and a primary wordmark. The
     * accent half is optional because a one-word brand is legitimate.
     *
     * Lengths mirror the column widths in `create_site_settings_table` — SQLite
     * ignores varchar limits, so validation is what actually stops an
     * over-long value reaching MySQL and being truncated or rejected there.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:100'],
            'brand_primary_text' => ['required', 'string', 'max:60'],
            'brand_accent_text' => ['nullable', 'string', 'max:60'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'footer_blurb' => ['nullable', 'string', 'max:1000'],
            'logo' => $this->uploadedImageRules(required: false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->uploadedImageMessages('logo', 'The site logo');
    }
}
