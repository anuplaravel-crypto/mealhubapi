<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\JsonResponse;

/**
 * Base for the self-service roles (customer, restaurant, rider) that share
 * the same registration rules and are created unverified pending OTP email
 * confirmation.
 */
abstract class SelfServiceAuthController extends BaseAuthController
{
    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->completeRegistration(
            $request->validated(),
            'Registration successful. Please verify your email with the OTP sent to your inbox.',
        );
    }
}
