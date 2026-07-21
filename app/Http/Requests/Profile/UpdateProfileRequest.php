<?php

namespace App\Http\Requests\Profile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The payload a signed-in user may send about themselves, for every role.
 *
 * `email` is deliberately absent: it identifies the account and anchors OTP
 * verification, so it cannot change through a plain profile save. So are
 * `role`, `status` and `is_email_verified` — a crafted payload carrying any of
 * them is ignored twice over, once by this whitelist and again by
 * `ProfileService::EDITABLE_FIELDS`.
 *
 * Limits match the `users` table column-for-column, and therefore also match
 * `Auth\RegisterRequest` — a value accepted at signup must still be acceptable
 * when it is edited.
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * The route is gated by `auth:sanctum` and the service acts on
     * `$request->user()` — never on an id from the payload — so there is no
     * per-record ownership to authorize here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:20'],
            'lastName' => ['nullable', 'string', 'max:20'],
            'mobile' => ['required', 'string', 'max:20'],
            'preferred_language' => ['nullable', 'string', 'max:20'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:50'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'county_id' => ['nullable', 'exists:counties,id'],
            'city_id' => ['nullable', 'exists:cities,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'firstName.required' => 'Please enter your first name.',
            'mobile.required' => 'Please enter your mobile number.',
        ];
    }
}
