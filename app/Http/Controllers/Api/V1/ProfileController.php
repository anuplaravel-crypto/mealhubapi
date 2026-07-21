<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfilePictureRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponse;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A signed-in user's own account details, one controller for all four roles.
 *
 * MealHub had four near-identical `ProfileController`s, one per role area,
 * because each lived behind its own guard and Blade layout. Here the role
 * arrives on the token, so the endpoints are shared and carry no `role:` gate —
 * every role is entitled to these, and none of them can reach anybody else's
 * row: the service acts on `$request->user()`, never on an id from the request.
 */
class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProfileService $profileService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($this->profileService->show($request->user())),
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->update($request->user(), $request->validated());

        return $this->successResponse(new UserResource($user), 'Profile updated successfully.');
    }

    /**
     * Separate from {@see self::update()} so changing a photo does not submit
     * the rest of the form with it.
     */
    public function updatePicture(UpdateProfilePictureRequest $request): JsonResponse
    {
        $user = $this->profileService->updatePicture($request->user(), $request->file('image'));

        return $this->successResponse(new UserResource($user), 'Profile picture updated successfully.');
    }
}
