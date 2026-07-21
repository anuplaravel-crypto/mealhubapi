<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rider\SaveVehicleRequest;
use App\Http\Resources\RiderVehicleResource;
use App\Http\Traits\ApiResponse;
use App\Services\Media\MediaPlacement;
use App\Services\RiderVehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The rider's own vehicle: read it, save it, and stream its photo.
 *
 * Role-gated to riders alone — unlike the profile and notification endpoints,
 * this is not something every role has a version of, so `role:rider` is a real
 * gate rather than a list of all four.
 *
 * None of the three actions takes an id. The vehicle served is the token
 * holder's own, which is why this controller needs no Policy; when Phase 11 adds
 * an admin read of a named rider's vehicle, *that* route takes an id and must
 * bring a Policy with it.
 *
 * The photo streams from here rather than from `Api/V1/MediaController`, whose
 * `show()` is the profile-picture path. Private reads stay with the domain that
 * owns the file — Phase 9's restaurant documents do the same — so the
 * authorization question for each file lives next to the rules that produced it.
 */
class VehicleController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RiderVehicleService $vehicleService,
    ) {}

    /**
     * The rider's registered vehicle. A rider who has not submitted one yet
     * gets a 404 from the service.
     */
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(
            new RiderVehicleResource($this->vehicleService->show($request->user())),
        );
    }

    /**
     * Create or update the vehicle — one endpoint for both, because a rider has
     * exactly one record and both submissions carry the same fields.
     *
     * A first submission answers 201, an edit 200, so the client can tell
     * "submitted for verification" from "sent back for re-verification" without
     * parsing the message.
     */
    public function save(SaveVehicleRequest $request): JsonResponse
    {
        $result = $this->vehicleService->save(
            $request->user(),
            $request->safe()->except('image'),
            $request->file('image'),
        );

        return $this->successResponse(
            new RiderVehicleResource($result['vehicle']),
            $result['is_new']
                ? 'Vehicle information saved. An admin will verify your documents and activate your account.'
                : 'Vehicle information updated. Your documents will be re-verified by an admin.',
            $result['is_new'] ? 201 : 200,
        );
    }

    /**
     * Stream the rider's own vehicle photo.
     *
     * `variant` comes straight off the query string and is not validated: an
     * unrecognised size degrades to `medium` inside `MediaPlacement`, so there
     * is no input here that can fail. A rider with no photo — or no vehicle at
     * all — is a 404 raised by the service.
     */
    public function image(Request $request): StreamedResponse
    {
        $variant = $request->query('variant');

        $path = $this->vehicleService->imagePath(
            $request->user(),
            is_string($variant) ? $variant : null,
        );

        return Storage::disk(MediaPlacement::Personal->disk())->response($path);
    }
}
