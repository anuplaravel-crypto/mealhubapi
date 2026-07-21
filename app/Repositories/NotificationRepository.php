<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Every Eloquent query against the `notifications` table.
 *
 * The model is Laravel's own `DatabaseNotification` — the `notifications`
 * migration is ported but there is no custom model, so this is the one
 * repository whose model does not live in `App\Models`.
 *
 * **Every read here is scoped to a notifiable.** Ids arrive from the URL on
 * four of the six endpoints, and the row they name is fetched by route-model
 * binding rather than by this class; ownership on that path is the
 * `NotificationPolicy`'s job. What these methods guarantee is the other half:
 * a list or a count can never include somebody else's row, whatever the caller
 * asks for.
 *
 * @extends BaseRepository<DatabaseNotification>
 */
class NotificationRepository extends BaseRepository
{
    protected function model(): string
    {
        return DatabaseNotification::class;
    }

    /**
     * One page of a user's full history (read and unread), newest first.
     *
     * The ordering comes from `Notifiable::notifications()`, which is a
     * `morphMany(...)->latest()`; adding another `latest()` here only emits a
     * duplicate `order by`.
     *
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function paginateFor(User $user, int $perPage): LengthAwarePaginator
    {
        return $user->notifications()->paginate($perPage);
    }

    /**
     * The newest unread notifications, capped — this feeds a bell dropdown,
     * which renders a handful and links to the paginated list for the rest.
     *
     * @return Collection<int, DatabaseNotification>
     */
    public function unreadFor(User $user, int $limit): Collection
    {
        return $user->unreadNotifications()->limit($limit)->get();
    }

    public function unreadCountFor(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markAsRead(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }

    /**
     * `markAsRead()` has no framework counterpart, so the column is written
     * directly — `read_at` is not fillable on `DatabaseNotification`.
     */
    public function markAsUnread(DatabaseNotification $notification): void
    {
        $notification->forceFill(['read_at' => null])->save();
    }

    /**
     * Mark every unread notification of a user as read in one statement, and
     * report how many rows that touched.
     *
     * Laravel's `$user->unreadNotifications->markAsRead()` loads the whole
     * unread set and saves each row; this is one UPDATE regardless of size.
     */
    public function markAllAsReadFor(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
