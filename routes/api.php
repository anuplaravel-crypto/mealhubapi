<?php

use App\Http\Controllers\Api\V1\Auth\AdminAuthController;
use App\Http\Controllers\Api\V1\Auth\CustomerAuthController;
use App\Http\Controllers\Api\V1\Auth\RestaurantAuthController;
use App\Http\Controllers\Api\V1\Auth\RiderAuthController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Role-scoped authentication (v1)
|--------------------------------------------------------------------------
|
| Every role shares the same set of endpoints via BaseAuthController; the
| controller fixes the role, so credentials for one role are never valid at
| another role's endpoints. Customer lives at the group root; admin,
| restaurant, and rider are namespaced under their own path prefix.
|
*/
$registerAuthRoutes = function (string $controller, string $prefix, string $role): void {
    Route::controller($controller)
        ->prefix($prefix)
        ->name("{$role}.")
        ->group(function () use ($role) {
            Route::post('registration', 'register')->name('registration');
            Route::post('verify-otp', 'verifyOtp')->middleware('throttle:6,1')->name('verify-otp');
            Route::post('resend-otp', 'resendOtp')->middleware('throttle:6,1')->name('resend-otp');
            Route::post('login', 'login')->middleware('throttle:6,1')->name('login');
            Route::post('forgot-password', 'forgotPassword')->middleware('throttle:6,1')->name('forgot-password');
            Route::post('reset-password', 'resetPassword')->middleware('throttle:6,1')->name('reset-password');

            // Authenticated actions carry role: as well as auth:sanctum. The
            // token proves identity; only the role gate proves the caller
            // belongs at *this* role's endpoints.
            Route::middleware(['auth:sanctum', "role:{$role}"])->group(function () {
                Route::post('change-password', 'changePassword')->name('change-password');
                Route::post('logout', 'logout')->name('logout');
            });
        });
};

Route::prefix('v1')->name('api.v1.')->group(function () use ($registerAuthRoutes) {
    $registerAuthRoutes(CustomerAuthController::class, '', 'customer');
    $registerAuthRoutes(AdminAuthController::class, 'admin', 'admin');
    $registerAuthRoutes(RestaurantAuthController::class, 'restaurant', 'restaurant');
    $registerAuthRoutes(RiderAuthController::class, 'rider', 'rider');

    /*
    |----------------------------------------------------------------------
    | Geo reference data (public, read-only)
    |----------------------------------------------------------------------
    |
    | The country -> county -> city cascade every registration form drives.
    | Public reference data with no per-user content, so no auth. The nested
    | paths express the hierarchy the client walks; an unknown parent id is a
    | 404 from route-model binding.
    |
    */
    Route::controller(LocationController::class)->group(function () {
        Route::get('countries', 'countries')->name('countries.index');
        Route::get('countries/{country}/counties', 'counties')->name('countries.counties.index');
        Route::get('counties/{county}/cities', 'cities')->name('counties.cities.index');
    });

    /*
    |----------------------------------------------------------------------
    | Public home CMS (public, read-only)
    |----------------------------------------------------------------------
    |
    | Everything the marketing home page renders — branding, navigation,
    | stats, sections, meal categories, featured restaurants and reviews —
    | in one anonymous read. Published content only; the admin write surface
    | arrives in Phase 10 under v1/admin/cms.
    |
    */
    Route::get('home', [HomeController::class, 'index'])->name('home');

    /*
    |----------------------------------------------------------------------
    | Own profile (every role)
    |----------------------------------------------------------------------
    |
    | Shared by all four roles and deliberately not role-gated: each of them
    | is entitled to maintain their own account, and none of these actions
    | takes an id — the caller is the token's user, so there is no other row
    | to reach. The picture is its own endpoint so a photo change does not
    | submit the rest of the form, and its read path streams from the private
    | disk rather than exposing a URL.
    |
    */
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('profile/picture', [ProfileController::class, 'updatePicture'])->name('profile.picture.update');
        Route::get('media/profile-picture', [MediaController::class, 'show'])->name('media.profile-picture');
    });

    /*
    |----------------------------------------------------------------------
    | In-app notifications (every role)
    |----------------------------------------------------------------------
    |
    | Shared by all four roles for the same reason the profile routes are:
    | every role has notifications, and a role: gate listing all four would
    | gate nothing. What makes these safe is different, though — four of them
    | take an id, so NotificationPolicy proves ownership inside the
    | controller. The two literal paths are declared before the {notification}
    | ones so `unread` and `read-all` are never read as an id.
    |
    */
    Route::middleware('auth:sanctum')
        ->controller(NotificationController::class)
        ->prefix('notifications')
        ->name('notifications.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('unread', 'unread')->name('unread');
            Route::patch('read-all', 'markAllAsRead')->name('read-all');
            Route::patch('{notification}/read', 'markAsRead')->name('read');
            Route::patch('{notification}/toggle-read', 'toggleRead')->name('toggle-read');
            Route::delete('{notification}', 'destroy')->name('destroy');
        });
});
