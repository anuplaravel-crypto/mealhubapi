<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization for acting on a *named* user row — the second Policy in the
 * codebase, and the first whose model is one of ours.
 *
 * Two abilities, one per route that names another user *and* streams one of
 * their private files: `GET v1/admin/restaurants/{restaurant}/documents/{slot}`
 * and `GET v1/admin/riders/{rider}/vehicle/image`. Everything else touching a
 * user is self-scoped (profile, notifications, rider vehicle, a restaurant's
 * own documents) and takes no id, so there is nothing to compare.
 *
 * The admin user-management routes take ids too and deliberately carry no
 * ability. They read and write the `users` row itself rather than a file, and
 * `UserRepository::findByRoleOrFail()` scopes the lookup by role, so a
 * wrong-collection id never resolves — an ability there could only re-check the
 * same column after a wider query had already found the row.
 *
 * **The roadmap called this `RestaurantDocumentPolicy`; that name would have
 * been a trap.** Policy discovery maps `App\Models\User` to `App\Policies\UserPolicy`,
 * so a differently-named class would need `Gate::policy(User::class, ...)` in
 * `AppServiceProvider` — which monopolises *every* future ability on `User` for
 * a class named after documents. The rider-vehicle ability below is that
 * prediction coming true, and it cost one method rather than a second policy
 * registration; the roadmap's separately-planned `RiderVehiclePolicy` was not
 * built for the same reason.
 */
class UserPolicy
{
    /**
     * Read a named restaurant's filed identity documents.
     *
     * Admins only, because verifying paperwork is their job and no other role
     * has any business reading a licence that names somebody else. The
     * restaurant's own read is deliberately absent rather than allowed here: it
     * goes through `v1/restaurant/documents/{slot}`, which takes no user id and
     * therefore asks no Policy. An owner branch on this ability would be a
     * permission no route can exercise, and an untestable one.
     *
     * The role check is the half `role:admin` cannot do. The middleware proves
     * the *caller* is an admin; only this proves the id in the URL names a
     * restaurant rather than a customer or a rider, whose `doc_image*` columns
     * exist on the shared `users` table and would otherwise resolve.
     */
    public function viewDocuments(User $actor, User $restaurant): bool
    {
        return $actor->role === 'admin' && $restaurant->role === 'restaurant';
    }

    /**
     * Read a named rider's vehicle photo.
     *
     * The ability Phase 8 deferred, for the same shape of route and the same
     * reasons as {@see self::viewDocuments()}: admins only, because verifying a
     * vehicle before activating a rider is their job and no other role has any
     * business seeing a photograph of somebody else's registration plate. The
     * rider's own read goes through `v1/rider/vehicle/image`, which takes no id
     * and therefore asks no Policy, so an owner branch here would be a
     * permission no route can exercise.
     *
     * The role check on the *target* is the half `role:admin` cannot do. Unlike
     * the document columns, a vehicle lives in its own table keyed on
     * `rider_id` — but `rider_id` is only a foreign key into the shared `users`
     * table, with no constraint that the row it names is a rider. Refusing a
     * non-rider id here is what keeps that assumption true.
     */
    public function viewVehicle(User $actor, User $rider): bool
    {
        return $actor->role === 'admin' && $rider->role === 'rider';
    }
}
