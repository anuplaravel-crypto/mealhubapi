<?php

namespace App\Http\Requests\Newsletter;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The public newsletter signup.
 */
class SubscribeRequest extends FormRequest
{
    /**
     * Deliberately public — this is the one write endpoint an anonymous
     * visitor is meant to reach. Abuse is handled by the route's throttle and
     * by double opt-in, not by authorization.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // No `unique` rule, on purpose. A repeat signup is a normal event
            // the service absorbs, and a uniqueness error would tell an
            // anonymous caller that the address is already on the list — the
            // membership disclosure the service's silent paths exist to avoid.
            //
            // `max:191` matches the column; `email:filter` is the permissive
            // check, since a stricter rule rejecting a deliverable address is
            // a worse failure here than accepting one that bounces.
            'email' => ['required', 'string', 'email:filter', 'max:191'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'That does not look like a valid email address.',
        ];
    }
}
