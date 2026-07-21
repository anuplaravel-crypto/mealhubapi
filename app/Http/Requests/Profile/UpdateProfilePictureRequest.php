<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\Concerns\ValidatesUploadedImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The picture-only save, kept as its own endpoint rather than an optional field
 * on {@see UpdateProfileRequest}.
 *
 * MealHub needed a third `UpdateProfileWithPictureRequest` because one Blade
 * form submitted both at once; an API has no such constraint, and separating
 * them means a photo change never carries along whatever half-edited name and
 * address the client is holding. The picture being the entire payload also
 * makes an empty submit a 422 rather than a silent no-op.
 */
class UpdateProfilePictureRequest extends FormRequest
{
    use ValidatesUploadedImage;

    /**
     * The route is gated by `auth:sanctum` and the service stores against
     * `$request->user()`, so there is no per-record ownership to authorize.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Format and size come from the shared upload trait, so raising the ceiling
     * or accepting a new format stays one edit for every upload in the API.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => $this->uploadedImageRules(required: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->uploadedImageMessages('image', 'The profile picture');
    }
}
