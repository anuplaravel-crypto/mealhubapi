<?php

namespace App\Providers;

use App\Policies\NotificationPolicy;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel discovers policies by mapping App\Models\X to
        // App\Policies\XPolicy. DatabaseNotification is the framework's own
        // model and lives in neither namespace, so nothing would find
        // NotificationPolicy without this line. Gate::authorize() denies an
        // ability it cannot resolve, so the failure mode is every notification
        // endpoint returning 403 — loud, but only if a test exercises them.
        Gate::policy(DatabaseNotification::class, NotificationPolicy::class);
    }
}
