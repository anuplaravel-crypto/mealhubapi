<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Ownership of one stored notification — the first Policy in the codebase.
 *
 * Everything before this phase was self-scoped: the profile and media
 * endpoints act on `$request->user()` and take no id, so there was nothing to
 * authorize. Four of the notification endpoints take an id from the URL, and
 * `notifications.id` is the *only* thing standing between a caller and another
 * user's row — so ownership is checked here rather than by scoping the lookup,
 * which would answer "not yours" with a 404 and quietly succeed on a delete
 * that matched nothing.
 *
 * `notifiable_type` is compared as well as `notifiable_id`: the table is
 * polymorphic, and ids collide across notifiable models. There is only one
 * notifiable today, which is precisely why the check is easy to omit and
 * expensive to add back later.
 *
 * Admins get no blanket override. An admin reading another user's
 * notifications is not a feature anything asks for, and `role:admin` on a
 * dedicated route is how it would arrive if it ever did.
 */
class NotificationPolicy
{
    /**
     * Read one notification. Not bound to a route today — the list endpoints
     * are scoped by the repository — but it is what `view` means here, and
     * leaving it out would make a later `GET /notifications/{id}` guess.
     */
    public function view(User $user, DatabaseNotification $notification): bool
    {
        return $this->owns($user, $notification);
    }

    /**
     * Change a notification's read state (mark-as-read and toggle).
     */
    public function update(User $user, DatabaseNotification $notification): bool
    {
        return $this->owns($user, $notification);
    }

    public function delete(User $user, DatabaseNotification $notification): bool
    {
        return $this->owns($user, $notification);
    }

    /**
     * Whether the notification was addressed to this exact notifiable.
     */
    private function owns(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && (string) $notification->notifiable_id === (string) $user->getKey();
    }
}
