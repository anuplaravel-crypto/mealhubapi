<?php

namespace App\Repositories;

use App\Models\RiderVehicle;
use App\Models\User;

/**
 * Every Eloquent query against the one-per-rider `rider_vehicles` rows.
 *
 * The primary key is almost never the way in: a rider has exactly one vehicle,
 * so both endpoints reach it through the owner. That is also what makes the
 * whole domain self-scoped — the lookups below take the authenticated `User`,
 * so no id from a request can point at another rider's row.
 *
 * @extends BaseRepository<RiderVehicle>
 */
class RiderVehicleRepository extends BaseRepository
{
    protected function model(): string
    {
        return RiderVehicle::class;
    }

    /**
     * The rider's vehicle, or null before they have registered one.
     *
     * The ordering guards the shape rather than relying on it: the relation is a
     * `hasMany` and the table has no unique index on `rider_id` alone, so a row
     * inserted outside {@see self::updateOrCreateForRider()} could make a second
     * one exist. Newest wins in that case rather than an arbitrary row.
     *
     * **The tie-break on `id` is what makes "newest" mean anything.** `latest()`
     * alone orders by `created_at`, whose resolution is one second — two rows
     * written in the same second leave the winner to whatever order the engine
     * happens to return, which is not a guarantee at all. The same tie-break
     * `paginateByRole()` applies for the same reason, and it also brings this
     * into line with `AdminUserResource::vehicle()`, which picks the highest id
     * off the eager-loaded relation.
     */
    public function forRider(User $rider): ?RiderVehicle
    {
        return $rider->vehicles()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Upsert the rider's single vehicle record, keyed on `rider_id`.
     *
     * Keying the upsert on the owner rather than on an id from the request is
     * what makes a save incapable of touching anybody else's row.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateOrCreateForRider(User $rider, array $data): RiderVehicle
    {
        return $this->query()->updateOrCreate(['rider_id' => $rider->getKey()], $data);
    }

    /**
     * Point every vehicle the rider owns at their new account status.
     *
     * Written as a mass update through the relation rather than a read-modify-
     * write on {@see self::forRider()} for two reasons: a rider with no vehicle
     * is a no-op rather than a null check at the call site, and the `latest()`
     * guard in `forRider()` deliberately ignores extra rows — which would leave
     * a stale second row claiming to be live for a deactivated account.
     *
     * @return int the number of vehicle rows updated
     */
    public function setActiveForRider(User $rider, bool $isActive): int
    {
        return $rider->vehicles()->update(['is_active' => $isActive]);
    }
}
