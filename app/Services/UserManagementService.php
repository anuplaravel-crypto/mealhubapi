<?php

namespace App\Services;

use App\Events\UserStatusChanged;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The admin's view of everybody else's account — list, read, and the one write
 * an admin has over another person's row: the activation gate.
 *
 * **One role-parameterized class replacing the reference app's three.** Its
 * `CustomerManagementService`, `RestaurantManagementService` and
 * `RiderManagementService` differed in exactly two places — the role string
 * they scoped by, and whether a toggle also touched a vehicle — and both of
 * those are arguments, not classes. This is the same call
 * {@see AuthService} and {@see ProfileService} already made.
 *
 * The role is never read off the request. It is fixed by the controller that
 * received the call, which is what makes `admin/customers/{id}` incapable of
 * resolving a rider: the scoping happens in the query, not in a check
 * afterwards.
 */
class UserManagementService
{
    /**
     * Rows per page when the caller does not ask for a size.
     *
     * Unlike {@see NewsletterService} this is only a default — the admin lists
     * are the first surface with a Form Request validating paging parameters,
     * so a client may ask for a different size within the bounds it enforces.
     */
    public const DEFAULT_PER_PAGE = 20;

    public function __construct(
        private readonly UserRepository $users,
    ) {}

    /**
     * One page of a role's accounts, filtered and sorted as the caller asked.
     *
     * `$filters` arrives already validated by `Admin\ListUsersRequest`; the
     * sort column in particular reaches `orderBy()` as a column name and must
     * never be an unchecked string.
     *
     * @param  array{search?: string|null, status?: bool|null, sort?: string|null, direction?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function list(string $role, array $filters = []): LengthAwarePaginator
    {
        return $this->users->paginateByRole(
            $role,
            $filters,
            $filters['per_page'] ?? self::DEFAULT_PER_PAGE,
        );
    }

    /**
     * A single account within one role.
     *
     * An id belonging to another role is a 404 from the scoped lookup rather
     * than a row this method then has to refuse — see
     * {@see UserRepository::findByRoleOrFail()} for why that stands in for a
     * Policy here.
     */
    public function show(string $role, int|string $id): User
    {
        return $this->users->findByRoleOrFail($id, $role);
    }

    /**
     * Flip the account's activation gate and announce it.
     *
     * A toggle rather than a set, for the same reason the CMS publish toggle
     * is: the caller sends no target state, so a replayed request cannot
     * reactivate an account an admin had just suspended.
     *
     * **Everything that follows from the flip hangs off the event, not off
     * this method.** `SendAccountStatusNotification` mails the user and
     * `SyncRiderVehicleStatus` follows a rider's vehicle to the new status —
     * neither is a line here, which is what the seam in
     * {@see UserStatusChanged} exists for.
     *
     * Deliberately **not** wrapped in a transaction. Two single-row updates in
     * separate listeners would need one only if a half-applied result were
     * unrecoverable, and it is not: `RiderVehicleService` re-derives
     * `is_active` from `users.status` on every save, so a vehicle left behind
     * is corrected the next time the rider touches it. A transaction here
     * would instead put mail delivery inside it, which is the failure mode
     * that actually costs something — an email announcing a rollback.
     *
     * @return array{user: User, activated: bool}
     */
    public function toggleStatus(string $role, int|string $id): array
    {
        $user = $this->users->findByRoleOrFail($id, $role);
        $activated = ! $user->status;

        $this->users->update($user, ['status' => $activated]);

        UserStatusChanged::dispatch($user, $activated);

        // Re-read rather than answering with `$user`. The listeners write
        // through the database — `SyncRiderVehicleStatus` updates the vehicle
        // rows in one statement — so the relations loaded a moment ago are
        // stale, and a response built from them would report a rider's vehicle
        // as still active after deactivating them.
        return ['user' => $this->users->findByRoleOrFail($id, $role), 'activated' => $activated];
    }
}
