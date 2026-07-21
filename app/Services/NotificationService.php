<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\NotificationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The in-app notification list every role reads, one service for all four.
 *
 * MealHub had an abstract `NotificationService` plus a subclass per role, and
 * the only thing the subclasses overrode was `extraPayload()` — extra keys
 * lifted out of the stored `data` blob because each role's popup JavaScript
 * read a different shape. An API has no popup: `NotificationResource` ships
 * `data` whole and the client picks what it renders. That removes the only
 * reason the subclasses existed, so they collapse the same way `AuthService`
 * and `ProfileService` did.
 *
 * The four id-taking actions take an already-resolved `DatabaseNotification`
 * rather than an id: route-model binding fetches it and `NotificationPolicy`
 * proves it belongs to the caller before it ever reaches this class.
 */
class NotificationService
{
    /**
     * Rows per page of the full history.
     *
     * Deliberately fixed rather than read from `?per_page=`: a client-supplied
     * page size is validated input, and this phase has no Form Request. Phase
     * 11's `Admin/ListUsersRequest` is where paging parameters get one.
     */
    private const PER_PAGE = 15;

    /**
     * How many unread notifications the bell endpoint returns. The count it
     * ships alongside them is the true total, not this slice's size.
     */
    private const UNREAD_LIMIT = 20;

    public function __construct(
        private readonly NotificationRepository $notifications,
    ) {}

    /**
     * One page of the caller's history, read and unread alike, newest first.
     *
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function paginateFor(User $user): LengthAwarePaginator
    {
        return $this->notifications->paginateFor($user, self::PER_PAGE);
    }

    /**
     * The bell payload: the newest unread notifications plus the unread total.
     *
     * The count is queried rather than derived from the capped list, so a user
     * with 200 unread sees 200 on the badge and 20 rows in the dropdown.
     *
     * @return array{count: int, notifications: Collection<int, DatabaseNotification>}
     */
    public function unreadFor(User $user): array
    {
        return [
            'count' => $this->notifications->unreadCountFor($user),
            'notifications' => $this->notifications->unreadFor($user, self::UNREAD_LIMIT),
        ];
    }

    /**
     * Mark one notification as read.
     *
     * Idempotent: marking an already-read notification leaves the original
     * `read_at` alone rather than moving it forward, so "when did I read this"
     * survives a client that retries.
     */
    public function markAsRead(DatabaseNotification $notification): DatabaseNotification
    {
        if ($notification->read_at === null) {
            $this->notifications->markAsRead($notification);
        }

        return $notification;
    }

    /**
     * Mark every unread notification of the caller as read, reporting how many
     * were still unread — a client that just cleared its badge can show it.
     */
    public function markAllAsRead(User $user): int
    {
        return $this->notifications->markAllAsReadFor($user);
    }

    /**
     * Flip one notification between read and unread.
     *
     * The unread direction is why this exists next to {@see self::markAsRead()}:
     * it is how a user re-flags something they opened by accident.
     */
    public function toggleRead(DatabaseNotification $notification): DatabaseNotification
    {
        $notification->read_at === null
            ? $this->notifications->markAsRead($notification)
            : $this->notifications->markAsUnread($notification);

        return $notification;
    }

    public function delete(DatabaseNotification $notification): void
    {
        $this->notifications->delete($notification);
    }
}
