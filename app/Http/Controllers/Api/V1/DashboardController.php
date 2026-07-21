<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Http\Traits\ApiResponse;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The landing payload every role reads for itself.
 *
 * One action replacing the reference app's four dashboard controllers, for the
 * same reason {@see ProfileController} replaced its four: each of those lived
 * behind its own guard and Blade layout, and here the role arrives on the token.
 *
 * Carries `auth:sanctum` and no `role:` gate, on the same terms as the profile
 * and notification routes — every role is entitled to its own dashboard, and a
 * gate listing all four would gate nothing. What makes that safe is that the
 * action takes **no id**: the payload is assembled from `$request->user()`, so
 * there is no other account any caller can reach and no Policy to write.
 */
class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    /**
     * The caller's own dashboard, shaped by the role their token carries.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->successResponse(
            new DashboardResource($this->dashboard->forUser($request->user())),
        );
    }
}
