<?php

namespace App\Events;

use App\Listeners\SendAccountStatusNotification;
use App\Listeners\SyncRiderVehicleStatus;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An admin activated or deactivated a user account.
 *
 * Fired by {@see UserManagementService::toggleStatus()}, the one write an admin
 * has over somebody else's row. It exists as an event because the reference app's
 * three management services each called `$user->notify(...)` inline, so "flip a
 * flag" and "send mail" were one statement — and the flip has a *second*
 * consequence besides mail: a rider's `RiderVehicle.is_active` follows their
 * status. Two listeners hang off this moment rather than two lines hanging off
 * the toggle, so a customer toggle carries no rider concern.
 *
 * @see SendAccountStatusNotification
 * @see SyncRiderVehicleStatus
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
