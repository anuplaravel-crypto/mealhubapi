<?php

namespace App\Listeners;

use App\Events\UserStatusChanged;
use App\Repositories\RiderVehicleRepository;
use App\Services\RiderVehicleService;
use App\Services\UserManagementService;

/**
 * Keeps a rider's vehicle in step with their account status.
 *
 * `rider_vehicles.is_active` is derived, never sent by the rider:
 * {@see RiderVehicleService} computes it from `users.status` on every save, so
 * the only way it can go stale is an admin flipping the account without the
 * vehicle following. That is precisely this listener.
 *
 * It is a listener rather than a second line in
 * {@see UserManagementService::toggleStatus()} because the reference app wrote
 * it as one, and the result was that "flip a flag" and "cascade to another
 * table" were the same statement — so the toggle had to know about vehicles,
 * and a customer toggle carried a rider's concern. The event is the seam;
 * `SendAccountStatusNotification` hangs off the same moment for the same
 * reason.
 *
 * Registered by Laravel's listener discovery, which scans `app/Listeners` for
 * a `handle()` with a type-hinted event. `php artisan event:list` is the check
 * that it is actually bound.
 */
class SyncRiderVehicleStatus
{
    public function __construct(
        private readonly RiderVehicleRepository $vehicles,
    ) {}

    /**
     * No-op for the three roles that own no vehicle, and for a rider who has
     * not registered one — the repository's mass update matches nothing rather
     * than needing a null check here.
     */
    public function handle(UserStatusChanged $event): void
    {
        if ($event->user->role !== 'rider') {
            return;
        }

        $this->vehicles->setActiveForRider($event->user, $event->activated);
    }
}
