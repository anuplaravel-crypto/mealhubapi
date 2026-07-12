<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Registration rules for self-service roles (customer, restaurant, rider).
 *
 * The role is fixed by the endpoint, not supplied by the client, so it is
 * intentionally not a validated field here.
 */
class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
            'email' => ['required', 'email', 'max:50', 'unique:users,email'],
            'mobile' => ['required', 'string', 'max:20'],
            'preferred_language' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'accept_registration_tnc' => ['required', 'accepted'],
            'marketing_consent' => ['nullable', 'boolean'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:50'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'county_id' => ['nullable', 'exists:counties,id'],
            'city_id' => ['nullable', 'exists:cities,id'],
        ];
    }
}
