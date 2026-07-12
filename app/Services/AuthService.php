<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\OtpNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const OTP_TTL_MINUTES = 10;

    /**
     * Register a new user for the given role.
     *
     * Admins are created pre-verified (no OTP email); customer, restaurant,
     * and rider accounts start unverified and are emailed a 6-digit OTP that
     * must be confirmed via verifyOtp() before they can log in.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, string $role): User
    {
        $isAdmin = $role === 'admin';

        $user = User::create([
            'firstName' => $data['firstName'],
            'lastName' => $data['lastName'] ?? null,
            'email' => Str::lower($data['email']),
            'mobile' => $data['mobile'] ?? null,
            'preferred_language' => $data['preferred_language'] ?? null,
            'role' => $role,
            'password' => $data['password'],
            'accept_registration_tnc' => $isAdmin ? true : (bool) ($data['accept_registration_tnc'] ?? false),
            'marketing_consent' => $data['marketing_consent'] ?? false,
            'address1' => $data['address1'] ?? null,
            'address2' => $data['address2'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'country_id' => $data['country_id'] ?? null,
            'county_id' => $data['county_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'otp' => $isAdmin ? $this->consumedOtpPlaceholder() : $this->generateOtp(),
            'otp_expires_at' => $isAdmin ? null : now()->addMinutes(self::OTP_TTL_MINUTES),
            'status' => true,
            'is_email_verified' => $isAdmin,
        ]);

        if (! $isAdmin) {
            $user->notify(new OtpNotification($user->otp, 'registration'));
        }

        return $user;
    }

    /**
     * Verify a registration OTP for the given role and issue an access token.
     *
     * @return array{0: User, 1: string}
     */
    public function verifyOtp(string $email, string $otp, string $role): array
    {
        $user = $this->findByEmailAndRole($email, $role);

        if (! $user || ! $this->otpIsValid($user, $otp)) {
            throw ValidationException::withMessages([
                'otp' => ['The provided OTP is invalid or has expired.'],
            ]);
        }

        $user->forceFill([
            'is_email_verified' => true,
            'otp' => $this->consumedOtpPlaceholder(),
            'otp_expires_at' => null,
        ])->save();

        return [$user, $user->createToken('auth-token')->plainTextToken];
    }

    /**
     * Authenticate a user of the given role and issue an access token.
     *
     * @return array{0: User, 1: string}
     */
    public function login(string $email, string $password, string $role): array
    {
        $user = $this->findByEmailAndRole($email, $role);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->is_email_verified) {
            throw ValidationException::withMessages([
                'email' => ['Please verify your email before logging in.'],
            ]);
        }

        if (! $user->status) {
            throw ValidationException::withMessages([
                'email' => ['Your account is inactive. Please contact support.'],
            ]);
        }

        return [$user, $user->createToken('auth-token')->plainTextToken];
    }

    /**
     * Email a password-reset OTP if the address belongs to a user of this role.
     *
     * Silently no-ops for unknown addresses so this endpoint never reveals
     * whether an email is registered under the given role.
     */
    public function forgotPassword(string $email, string $role): void
    {
        $user = $this->findByEmailAndRole($email, $role);

        if (! $user) {
            return;
        }

        $user->forceFill([
            'otp' => $this->generateOtp(),
            'otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ])->save();

        $user->notify(new OtpNotification($user->otp, 'password_reset'));
    }

    /**
     * Verify a password-reset OTP for the given role and set the new password.
     */
    public function resetPassword(string $email, string $otp, string $password, string $role): void
    {
        $user = $this->findByEmailAndRole($email, $role);

        if (! $user || ! $this->otpIsValid($user, $otp)) {
            throw ValidationException::withMessages([
                'otp' => ['The provided OTP is invalid or has expired.'],
            ]);
        }

        $user->forceFill([
            'password' => $password,
            'otp' => $this->consumedOtpPlaceholder(),
            'otp_expires_at' => null,
        ])->save();

        // Invalidate existing sessions since the password just changed.
        $user->tokens()->delete();
    }

    /**
     * Revoke the token used to make the current request.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    private function findByEmailAndRole(string $email, string $role): ?User
    {
        return User::where('role', $role)
            ->where('email', Str::lower($email))
            ->first();
    }

    private function otpIsValid(User $user, string $otp): bool
    {
        return $user->otp === $otp
            && $user->otp_expires_at !== null
            && $user->otp_expires_at->isFuture();
    }

    private function generateOtp(): string
    {
        return (string) random_int(100000, 999999);
    }

    /**
     * The otp column is not nullable, so a spent OTP is replaced with a
     * value that can never match a real (numeric) code instead of null.
     */
    private function consumedOtpPlaceholder(): string
    {
        return Str::random(6);
    }
}
