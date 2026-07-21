<?php

use App\Http\Controllers\Api\V1\Auth\AdminAuthController;
use App\Http\Controllers\Api\V1\Auth\CustomerAuthController;
use App\Http\Controllers\Api\V1\Auth\RestaurantAuthController;
use App\Http\Controllers\Api\V1\Auth\RiderAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

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
});
