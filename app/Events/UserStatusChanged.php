<?php

namespace App\Events;

use App\Listeners\SendAccountStatusNotification;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An admin activated or deactivated a user account.
 *
 * Net-new, with no producer yet: Phase 11's admin toggle is what fires it. It
 * exists now because the reference app's three management services each called
 * `$user->notify(...)` inline, so "flip a flag" and "send mail" were one
 * statement — and Phase 11 has a second consequence to hang off the same
 * moment (a deactivated rider's `RiderVehicle.is_active` follows their status).
 * A second listener is the place for that, not a second line in the toggle.
 *
 * @see SendAccountStatusNotification
 */
class UserStatusChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param  User  $user  the account whose status changed
     * @param  bool  $activated  the status it changed *to*
     */
    public function __construct(
        public readonly User $user,
        public readonly bool $activated,
    ) {}
}
