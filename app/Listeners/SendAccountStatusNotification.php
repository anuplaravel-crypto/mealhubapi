<?php

namespace App\Listeners;

use App\Events\UserStatusChanged;
use App\Notifications\AccountStatusNotification;

/**
 * Emails and stores the account-status notice whenever an admin flips a user's
 * status.
 *
 * Registered by Laravel's listener discovery, which scans `app/Listeners` for
 * a `handle()` with a type-hinted event — there is no provider entry to keep
 * in sync. `php artisan event:list` is the check that it is actually bound.
 */
class SendAccountStatusNotification
{
    public function handle(UserStatusChanged $event): void
    {
        $event->user->notify(new AccountStatusNotification($event->activated));
    }
}
