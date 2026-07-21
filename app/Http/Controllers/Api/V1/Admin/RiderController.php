<?php

namespace App\Http\Controllers\Api\V1\Admin;

/**
 * Admin management of rider accounts.
 *
 * The only one of the three whose toggle has a second consequence: activating a
 * rider also activates their vehicle. That does not appear here, or in the
 * service — `SyncRiderVehicleStatus` listens for `UserStatusChanged` and does
 * it, so the customer and restaurant toggles carry no rider concern.
 *
 * The vehicle itself rides along on the response through `AdminUserResource`;
 * its photo streams from {@see RiderVehicleController}, which binds the rider
 * and asks a Policy, exactly as the restaurant document read does.
 */
class RiderController extends BaseUserManagementController
{
    protected function role(): string
    {
        return 'rider';
    }

    protected function label(): string
    {
        return 'Rider';
    }
}
