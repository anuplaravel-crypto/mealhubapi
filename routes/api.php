<?php

use App\Http\Controllers\Api\V1\Admin\Cms\SiteSettingController as CmsSiteSettingController;
use App\Http\Controllers\Api\V1\Admin\Cms\TestimonialController as CmsTestimonialController;
use App\Http\Controllers\Api\V1\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\V1\Admin\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\Api\V1\Admin\RestaurantController as AdminRestaurantController;
use App\Http\Controllers\Api\V1\Admin\RestaurantDocumentController as AdminRestaurantDocumentController;
use App\Http\Controllers\Api\V1\Admin\RiderController as AdminRiderController;
use App\Http\Controllers\Api\V1\Admin\RiderVehicleController as AdminRiderVehicleController;
use App\Http\Controllers\Api\V1\Auth\AdminAuthController;
use App\Http\Controllers\Api\V1\Auth\CustomerAuthController;
use App\Http\Controllers\Api\V1\Auth\RestaurantAuthController;
use App\Http\Controllers\Api\V1\Auth\RiderAuthController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\NewsletterController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\Restaurant\DocumentController as RestaurantDocumentController;
use App\Http\Controllers\Api\V1\Rider\VehicleController;
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

/*
|--------------------------------------------------------------------------
| Admin user management (v1)
|--------------------------------------------------------------------------
|
| The same three actions over each of the three managed roles, differing only
| in the path segment and the controller that fixes the role. Registered from
| one closure for the same reason the auth routes are: three copies of the
| block below is three places to forget `whereNumber`.
|
| The path segment doubles as the route-name segment — `admin.customers.index`,
| `admin.riders.toggle-status` — so there is no second string to keep in step
| with the first.
|
*/
$registerUserManagementRoutes = function (string $controller, string $segment): void {
    Route::controller($controller)
        ->prefix($segment)
        ->name("{$segment}.")
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{id}', 'show')->whereNumber('id')->name('show');
            Route::patch('{id}/toggle-status', 'toggleStatus')->whereNumber('id')->name('toggle-status');
        });
};

Route::prefix('v1')->name('api.v1.')->group(function () use ($registerAuthRoutes, $registerUserManagementRoutes) {
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

    /*
    |----------------------------------------------------------------------
    | Newsletter (public)
    |----------------------------------------------------------------------
    |
    | Double opt-in: an address is not on the list until the person who owns
    | it confirms from the emailed link. All three are anonymous — a
    | subscriber has no account, and the token is what proves anything.
    |
    | The two link endpoints are POST rather than the roadmap's GET. The
    | emailed link lands on the SPA, which then calls the API, so nothing
    | requires these to be reachable by a browser following a URL — and a
    | state change that a crawler or link preview can trigger is exactly
    | what a GET here would be.
    |
    | Throttled per IP by the default `throttle` behavior for guests.
    | Signup is the tighter limit: it is the one that costs a real person an
    | unsolicited email. Guessing a 64-character token is not the threat the
    | limit on the other two answers — repeated automated calls are.
    |
    */
    Route::controller(NewsletterController::class)
        ->prefix('newsletter')
        ->name('newsletter.')
        ->group(function () {
            Route::post('subscribe', 'subscribe')->middleware('throttle:5,1')->name('subscribe');
            Route::post('confirm/{token}', 'confirm')->middleware('throttle:10,1')->name('confirm');
            Route::post('unsubscribe/{token}', 'unsubscribe')->middleware('throttle:10,1')->name('unsubscribe');
        });

    /*
    |----------------------------------------------------------------------
    | Rider vehicle (rider only)
    |----------------------------------------------------------------------
    |
    | Rider onboarding's one extra step: the vehicle an admin verifies before
    | activating the account. Unlike the profile and notification routes this
    | is not something every role has a version of, so `role:rider` is a real
    | gate rather than a list of all four.
    |
    | No id appears in any of the three paths — a rider has exactly one
    | vehicle and reaches it through their own token — so none of them needs a
    | Policy. The admin read of a *named* rider's vehicle is registered at the
    | bottom of this file, takes an id, and carries one.
    |
    | `save` is POST for both create and update: the record is an upsert, and
    | PHP does not populate an uploaded file bag on a PUT body.
    |
    */
    Route::middleware(['auth:sanctum', 'role:rider'])
        ->controller(VehicleController::class)
        ->prefix('rider/vehicle')
        ->name('rider.vehicle.')
        ->group(function () {
            Route::get('/', 'show')->name('show');
            Route::post('/', 'save')->name('save');
            Route::get('image', 'image')->name('image');
        });

    /*
    |----------------------------------------------------------------------
    | Restaurant documents (restaurant only)
    |----------------------------------------------------------------------
    |
    | The identity paperwork an admin verifies before activating a restaurant.
    | Self-scoped like the rider's vehicle: every action works on the token's
    | user and none takes an id, so none needs a Policy. The admin read of a
    | named restaurant's documents is the route below, and does.
    |
    | `save` is POST for create and correction alike — it is an upsert of two
    | columns, and PHP populates no uploaded-file bag on a PUT body. The
    | download path is declared with whereNumber so a slot can never be read
    | as anything else.
    |
    */
    Route::middleware(['auth:sanctum', 'role:restaurant'])
        ->controller(RestaurantDocumentController::class)
        ->prefix('restaurant/documents')
        ->name('restaurant.documents.')
        ->group(function () {
            Route::get('/', 'show')->name('show');
            Route::post('/', 'save')->name('save');
            Route::get('{slot}', 'download')->whereNumber('slot')->name('download');
        });

    /*
    |----------------------------------------------------------------------
    | Restaurant documents, admin read
    |----------------------------------------------------------------------
    |
    | The first route in the codebase that names *another user*. `role:admin`
    | proves the caller is an admin; it does not prove `{restaurant}` names a
    | restaurant, so `UserPolicy::viewDocuments()` decides — an id pointing at
    | a customer or a rider is refused there rather than streaming a file the
    | slot map would happily resolve.
    |
    */
    Route::middleware(['auth:sanctum', 'role:admin'])
        ->get('admin/restaurants/{restaurant}/documents/{slot}', [AdminRestaurantDocumentController::class, 'show'])
        ->whereNumber('slot')
        ->name('admin.restaurants.documents.show');

    /*
    |----------------------------------------------------------------------
    | Newsletter administration
    |----------------------------------------------------------------------
    |
    | Read and delete only — there is deliberately no admin "add subscriber".
    | `role:admin` is the whole authorization question here: a subscriber has
    | no owner, so the id on the delete route needs no Policy to scope it.
    |
    */
    Route::middleware(['auth:sanctum', 'role:admin'])
        ->controller(AdminNewsletterController::class)
        ->prefix('admin/newsletter')
        ->name('admin.newsletter.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::delete('{subscriber}', 'destroy')->name('destroy');
        });

    /*
    |----------------------------------------------------------------------
    | Home CMS administration
    |----------------------------------------------------------------------
    |
    | The write half of the content an anonymous visitor reads at v1/home.
    | `role:admin` is the whole authorization question — a CMS record has no
    | owner, so the ids below need no Policy, exactly as the newsletter
    | delete above does not. Anything with an *owner* still does.
    |
    | Edits are POST rather than PUT wherever the payload can carry a file:
    | PHP populates no uploaded-file bag on a PUT body, which is the same
    | constraint the rider vehicle and restaurant document upserts answer.
    | Everything else keeps a real verb — PATCH for a one-field toggle,
    | DELETE for a removal — so MealHub's `POST .../{id}/delete` Blade
    | workaround does not survive the port.
    |
    | Site settings is a singleton: read and save, with no list, create,
    | delete or toggle to route.
    |
    */
    Route::middleware(['auth:sanctum', 'role:admin'])
        ->prefix('admin/cms')
        ->name('admin.cms.')
        ->group(function () {
            Route::controller(CmsSiteSettingController::class)->group(function () {
                Route::get('site-settings', 'show')->name('site-settings.show');
                Route::post('site-settings', 'update')->name('site-settings.update');
            });

            Route::controller(CmsTestimonialController::class)
                ->prefix('testimonials')
                ->name('testimonials.')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::post('{id}', 'update')->whereNumber('id')->name('update');
                    Route::patch('{id}/toggle', 'toggle')->whereNumber('id')->name('toggle');
                    Route::delete('{id}', 'destroy')->whereNumber('id')->name('destroy');
                });
        });

    /*
    |----------------------------------------------------------------------
    | User administration
    |----------------------------------------------------------------------
    |
    | The admin's view of everybody else's account: one list, one profile
    | read, and one write — the activation gate. Three identical route sets,
    | registered from one closure for the same reason the auth routes are,
    | with the *role* fixed by the controller rather than taken from the
    | path. Nothing a client sends can widen a customer list into everybody.
    |
    | The ids here carry no Policy, and that is the considered call rather
    | than the newsletter/CMS exception repeated. There is no ownership to
    | check — an admin does not own a customer — and the two questions that
    | do matter are answered elsewhere: `role:admin` proves the caller, and
    | `UserRepository::findByRoleOrFail()` proves the target by scoping the
    | lookup, so an id naming a rider simply does not exist under
    | `admin/customers`. A wrong-collection id is a 404, not a 403 — a 403
    | would confirm the row exists somewhere.
    |
    | `toggle-status` is PATCH and sends no target state: a replayed request
    | cannot reactivate an account an admin had just suspended.
    |
    | The last route is the exception that proves the rule. It names another
    | user *and* streams a private file, so it binds the model and asks
    | `UserPolicy::viewVehicle()` — the ability Phase 8 deferred because
    | nothing in that phase took an id. Its sibling for restaurant paperwork
    | is registered above, under the same reasoning.
    |
    */
    Route::middleware(['auth:sanctum', 'role:admin'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () use ($registerUserManagementRoutes) {
            $registerUserManagementRoutes(AdminCustomerController::class, 'customers');
            $registerUserManagementRoutes(AdminRestaurantController::class, 'restaurants');
            $registerUserManagementRoutes(AdminRiderController::class, 'riders');

            Route::get('riders/{rider}/vehicle/image', [AdminRiderVehicleController::class, 'image'])
                ->whereNumber('rider')
                ->name('riders.vehicle.image');
        });
});
