<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Change the password of the already-authenticated user, as opposed to the
 * OTP-driven reset flow, which is for users who are locked out.
 */
class ChangePasswordRequest extends FormRequest
{
    /**
     * The route is already gated by `auth:sanctum`, and the service acts on
     * `$request->user()` — never on an id from the body — so there is no
     * per-record ownership to check here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `current_password:sanctum` re-verifies the old password against the
     * token's user, so a stolen-but-unexpired token cannot be used to lock
     * the real owner out. The reference app hand-rolled this check with a
     * closure because it authenticated via JWT rather than a guard; with
     * Sanctum the built-in rule works.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.different' => 'The new password must be different from your current password.',
        ];
    }
}
