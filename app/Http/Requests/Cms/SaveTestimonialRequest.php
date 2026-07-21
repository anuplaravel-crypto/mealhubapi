<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\Concerns\ValidatesUploadedImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The create-or-edit payload for one testimonial.
 *
 * One request for both, because the rules are identical either way — an avatar
 * is optional on a new review and on an edit — so splitting this into a store
 * and an update request would produce two identical classes.
 */
class SaveTestimonialRequest extends FormRequest
{
    use ValidatesUploadedImage;

    /**
     * The route carries `auth:sanctum` + `role:admin`. A testimonial has no
     * owner to check the caller against — being an admin is the whole
     * authorization question, exactly as it is for newsletter subscribers — so
     * this domain has no Policy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `avatar` and `avatar_url` may both be submitted, but they will not both
     * survive: the service clears the external link whenever an upload is
     * saved, because the Resource resolves an upload ahead of a link.
     *
     * `is_published` is optional and, when omitted on an edit, leaves the flag
     * as it was rather than hiding the review — publishing state has its own
     * toggle endpoint, so a save is never the thing that takes a review down.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quote' => ['required', 'string', 'max:1000'],
            'author_name' => ['required', 'string', 'max:100'],
            'author_role' => ['nullable', 'string', 'max:100'],
            'avatar' => $this->uploadedImageRules(required: false),
            'avatar_url' => ['nullable', 'url', 'max:500'],
            'rating' => ['required', 'numeric', 'between:0,5'],
            'is_published' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->uploadedImageMessages('avatar', 'The avatar');
    }
}
