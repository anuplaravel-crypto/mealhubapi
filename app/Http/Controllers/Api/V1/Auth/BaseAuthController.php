<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponse;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared auth endpoints for every role. Each concrete controller only
 * declares which role it serves; all lookups and account creation are
 * scoped to that role inside AuthService, so e.g. a customer can never
 * authenticate through the admin endpoints.
 */
abstract class BaseAuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected readonly AuthService $authService,
    ) {}

    /**
     * The role this controller authenticates (customer, admin, restaurant, rider).
     */
    abstract protected function role(): string;

    /**
     * Create the account and shape the registration response. Concrete
     * controllers call this from their own role-specific `register()` so each
     * can type-hint the Form Request whose rules apply to its role.
     *
     * @param  array<string, mixed>  $data
     */
    protected function completeRegistration(array $data, string $message): JsonResponse
    {
        $user = $this->authService->register($data, $this->role());

        return $this->successResponse(new UserResource($user), $message, 201);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        [$user, $token] = $this->authService->verifyOtp(
            $request->validated('email'),
            $request->validated('otp'),
            $this->role(),
        );

        return $this->successResponse([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Email verified successfully.');
    }

    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $this->authService->resendOtp($request->validated('email'), $this->role());

        return $this->successResponse(
            null,
            'If that account exists and is not yet verified, a new code has been sent.',
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        [$user, $token] = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
            $this->role(),
        );

        return $this->successResponse([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Login successful.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->validated('email'), $this->role());

        return $this->successResponse(
            null,
            'If an account with that email exists, a password reset code has been sent.',
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword(
            $request->validated('email'),
            $request->validated('otp'),
            $request->validated('password'),
            $this->role(),
        );

        return $this->successResponse(
            null,
            'Password reset successfully. Please log in with your new password.',
        );
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->user(),
            $request->validated('password'),
        );

        return $this->successResponse(
            null,
            'Password changed successfully. Your other devices have been signed out.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->successResponse(null, 'Logged out successfully.');
    }
}
