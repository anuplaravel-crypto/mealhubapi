<?php

namespace App\Notifications\Concerns;

use App\Models\User;

/**
 * Shared formatting for the "personal details" block every admin-facing
 * notification carries — in the email body and in the stored payload.
 *
 * Ported unchanged from the reference app, where two of the three registration
 * notifications used it and the third had copy-pasted private equivalents that
 * had already drifted. One role-parameterized notification makes that
 * impossible now, but the trait stays: Phases 8 and 9 raise the same block for
 * vehicle and document uploads.
 */
trait FormatsUserDetails
{
    protected function fullName(User $user): string
    {
        return trim(($user->firstName ?? '').' '.($user->lastName ?? ''));
    }

    /**
     * Human-readable address: street, city, county, country, zip — skipping
     * blanks, since every part below `address1` is optional at registration.
     */
    protected function fullAddress(User $user): string
    {
        $parts = array_filter([
            $user->address1,
            $user->city?->name,
            $user->county?->name,
            $user->country?->name,
            $user->zip_code,
        ]);

        return $parts !== [] ? implode(', ', $parts) : 'Not provided';
    }

    /**
     * The personal-details payload stored on an admin-facing notification.
     *
     * @return array<string, mixed>
     */
    protected function personalDetails(User $user): array
    {
        return [
            'name' => $this->fullName($user),
            'mobile' => $user->mobile,
            'email' => $user->email,
            'address' => $this->fullAddress($user),
        ];
    }
}
