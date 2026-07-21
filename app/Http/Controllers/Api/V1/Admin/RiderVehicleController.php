<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Media\MediaPlacement;
use App\Services\RiderVehicleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * An admin reading a named rider's vehicle photo, so the account can be
 * verified.
 *
 * The Policy the roadmap deferred out of Phase 8 arrives here. Nothing in that
 * phase took an id — a rider reaches their own vehicle through their token — so
 * there was no route to bind an ability to. This is that route: it names
 * another user, and hands back a photograph of a real person's registration
 * plate off the private disk.
 *
 * Separated from {@see RiderController} on the same reasoning that keeps
 * {@see RestaurantDocumentController} apart from the restaurant's own: the
 * self-scoped actions take no id and ask no Policy, and keeping the two shapes
 * in different classes means a self-scoped action cannot quietly acquire an id
 * without somebody noticing what that implies.
 *
 * `role:admin` on the route is not the whole answer either — it proves the
 * caller is an admin, not that `{rider}` names a rider. A bound id pointing at
 * a customer or a restaurant is refused by `UserPolicy::viewVehicle()`.
 */
class RiderVehicleController extends Controller
{
    public function __construct(
        private readonly RiderVehicleService $vehicleService,
    ) {}

    /**
     * Stream the named rider's vehicle photo.
     *
     * Defaults to the `large` variant rather than `medium`, the same call
     * `RestaurantDocumentController` makes: an admin is reading a plate off the
     * photo, not glancing at a thumbnail.
     */
    public function image(Request $request, User $rider): StreamedResponse
    {
        Gate::authorize('viewVehicle', $rider);

        $variant = $request->query('variant');

        $path = $this->vehicleService->imagePath(
            $rider,
            is_string($variant) ? $variant : 'large',
        );

        return Storage::disk(MediaPlacement::Personal->disk())->response($path);
    }
}
