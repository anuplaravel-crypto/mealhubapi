<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\CountyResource;
use App\Http\Traits\ApiResponse;
use App\Models\Country;
use App\Models\County;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;

/**
 * Public read-only geo reference data, served as the country -> county -> city
 * cascade the registration forms step through. There is nothing to validate —
 * both child endpoints take a route-model-bound parent, so an unknown id is a
 * 404 from the binding rather than a request rule.
 */
class LocationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly LocationService $locationService,
    ) {}

    public function countries(): JsonResponse
    {
        return $this->successResponse(
            CountryResource::collection($this->locationService->countries()),
        );
    }

    public function counties(Country $country): JsonResponse
    {
        return $this->successResponse(
            CountyResource::collection($this->locationService->countiesForCountry($country)),
        );
    }

    public function cities(County $county): JsonResponse
    {
        return $this->successResponse(
            CityResource::collection($this->locationService->citiesForCounty($county)),
        );
    }
}
