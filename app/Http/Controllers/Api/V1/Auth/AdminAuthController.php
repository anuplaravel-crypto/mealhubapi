<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Auth\AdminRegisterRequest;
use Illuminate\Http\JsonResponse;

class AdminAuthController extends BaseAuthController
{
    protected function role(): string
    {
        return 'admin';
    }

    /**
     * Admins register with a reduced field set and are created pre-verified,
     * so no OTP email is sent and they can log in immediately.
     */
    public function register(AdminRegisterRequest $request): JsonResponse
    {
        return $this->completeRegistration(
            $request->validated(),
            'Registration successful. You can log in now.',
        );
    }
}
