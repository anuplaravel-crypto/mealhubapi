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
     * `latest()` guards the shape rather than relying on it: the relation is a
     * `hasMany` and the table has no unique index on `rider_id` alone, so a row
     * inserted outside {@see self::updateOrCreateForRider()} could make a second
     * one exist. Newest wins in that case rather than an arbitrary row.
     */
    public function forRider(User $rider): ?RiderVehicle
    {
        return $rider->vehicles()->latest()->first();
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
}
