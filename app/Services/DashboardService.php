<?php

namespace App\Services;

use App\Models\RiderVehicle;
use App\Models\User;
use App\Repositories\NotificationRepository;
use App\Repositories\RiderVehicleRepository;
use App\Repositories\UserRepository;

/**
 * What a signed-in user sees when they land — one service for all four roles.
 *
 * **One role-switched class replacing the reference app's four.** The roadmap
 * predicted its `AdminDashboardService`, `CustomerDashboardService`,
 * `RestaurantDashboardService` and `RiderDashboardService` "genuinely differ per
 * role"; reading them, three are a single `findById` wrapper and the fourth adds
 * two derived booleans. The difference is a `match` arm, not a class — the same
 * collapse {@see AuthService}, {@see ProfileService} and
 * {@see UserManagementService} already made.
 *
 * **This is not a second profile endpoint.** Nearly everything the reference
 * dashboards render is the profile pane, which `GET v1/profile` already serves;
 * repeating the address and the three location rows here would mean two
 * endpoints to keep in step every time the profile grows a field. What this adds
 * is the part a profile cannot answer: how far through onboarding the account
 * is, and — for an admin — how many accounts are waiting.
 *
 * Like the profile and notification services it acts on `$request->user()` and
 * never on an id, so there is no other row any caller can reach. The role is
 * read off the authenticated user rather than the request for the same reason
 * `UserManagementService` takes it from the controller: a role a client can
 * choose is a role a client can forge.
 */
class DashboardService
{
    /**
     * The roles an admin's tiles count, in the order they are rendered.
     *
     * Deliberately not "every role": these are exactly the three collections
     * `v1/admin/{customers,restaurants,riders}` lists, so every tile leads
     * somewhere. Admins are absent for the same reason Phase 11 made them
     * unreachable through those lists — an admin is not somebody an admin
     * manages.
     *
     * @var list<string>
     */
    public const COUNTED_ROLES = ['customer', 'restaurant', 'rider'];

    public function __construct(
        private readonly UserRepository $users,
        private readonly NotificationRepository $notifications,
        private readonly RiderVehicleRepository $vehicles,
    ) {}

    /**
     * Everything the caller's dashboard renders, assembled for their role.
     *
     * The shape is deliberately flat and the Resource does the nesting — the
     * service's job is deciding *what* is fetched, and a payload built here
     * would put wire formatting in the tier that must not hold it.
     *
     * Cost is fixed and small: one query for the unread badge, plus at most one
     * more for the role's own section. A customer's and a restaurant's sections
     * cost nothing at all — email verification and the two document columns are
     * already on the row the token resolved.
     *
     * @return array{
     *     user: User,
     *     unread_notifications: int,
     *     vehicle?: ?RiderVehicle,
     *     user_counts?: array<string, array{total: int, active: int, inactive: int}>,
     * }
     */
    public function forUser(User $user): array
    {
        return [
            'user' => $user,
            'unread_notifications' => $this->notifications->unreadCountFor($user),
            ...$this->sectionFor($user),
        ];
    }

    /**
     * The one extra read a role's dashboard needs, or nothing.
     *
     * @return array<string, mixed>
     */
    private function sectionFor(User $user): array
    {
        return match ($user->role) {
            'admin' => ['user_counts' => $this->userCounts()],
            'rider' => ['vehicle' => $this->vehicles->forRider($user)],
            default => [],
        };
    }

    /**
     * The three account tiles, every role present even at zero.
     *
     * The repository returns only roles that have accounts — filling the gaps
     * is this method's job, because "which tiles exist" is a decision about the
     * dashboard rather than about the table. Without it a fresh install would
     * ship a dashboard with missing tiles rather than three zeroes, and a client
     * would have to treat an absent key and a zero as the same thing.
     *
     * @return array<string, array{total: int, active: int, inactive: int}>
     */
    private function userCounts(): array
    {
        $tallied = $this->users->countsByRole(self::COUNTED_ROLES);

        return collect(self::COUNTED_ROLES)
            ->mapWithKeys(function (string $role) use ($tallied): array {
                $row = $tallied->get($role);
                $total = (int) ($row->total ?? 0);
                $active = (int) ($row->active ?? 0);

                return [$role => [
                    'total' => $total,
                    'active' => $active,
                    'inactive' => $total - $active,
                ]];
            })
            ->all();
    }
}
