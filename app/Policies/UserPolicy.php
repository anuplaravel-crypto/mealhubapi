<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization for acting on a *named* user row — the second Policy in the
 * codebase, and the first whose model is one of ours.
 *
 * Only one ability so far, because only one route names another user:
 * `GET v1/admin/restaurants/{restaurant}/documents/{slot}`. Everything else
 * touching a user is self-scoped (profile, notifications, rider vehicle,
 * a restaurant's own documents) and takes no id, so there is nothing to compare.
 *
 * **The roadmap called this `RestaurantDocumentPolicy`; that name would have
 * been a trap.** Policy discovery maps `App\Models\User` to `App\Policies\UserPolicy`,
 * so a differently-named class would need `Gate::policy(User::class, ...)` in
 * `AppServiceProvider` — which monopolises *every* future ability on `User` for
 * a class named after documents. Phase 11's admin user management gains its
 * abilities here instead.
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
}
