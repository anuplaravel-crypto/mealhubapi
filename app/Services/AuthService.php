<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\OtpNotification;
use App\Notifications\RegistrationNotification;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const OTP_TTL_MINUTES = 10;

    public function __construct(
        private readonly UserRepository $users,
    ) {}

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

        $user = $this->users->create([
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
            $user->notify(new OtpNotification($user->otp, 'registration', $role));

            // Admins are told about the signup immediately, not on verification:
            // a restaurant or rider is waiting on an admin to approve them, and
            // the queue an admin works from should not depend on whether the
            // applicant has opened their email yet. An admin registering
            // another admin raises nothing — that is not news to anybody.
            Notification::send($this->users->admins(), new RegistrationNotification($user));
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

        $this->users->update($user, [
            'is_email_verified' => true,
            'otp' => $this->consumedOtpPlaceholder(),
            'otp_expires_at' => null,
        ]);

        return [$user, $this->users->issueToken($user, 'auth-token')];
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

        return [$user, $this->users->issueToken($user, 'auth-token')];
    }

    /**
     * Re-send the registration OTP if the address belongs to an as-yet
     * unverified user of this role.
     *
     * Like forgotPassword(), this silently no-ops for an unknown address —
     * and for an already-verified one — so the endpoint never reveals
     * whether an email is registered, or how far through signup it is.
     */
    public function resendOtp(string $email, string $role): void
    {
        $user = $this->findByEmailAndRole($email, $role);

        if (! $user || $user->is_email_verified) {
            return;
        }

        $this->users->update($user, [
            'otp' => $this->generateOtp(),
            'otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        $user->notify(new OtpNotification($user->otp, 'registration', $role));
    }

    /**
     * Change the password of an already-authenticated user.
     *
     * The current password is verified by ChangePasswordRequest, so by the
     * time this runs the caller has proven they know it.
     */
    public function changePassword(User $user, string $password): void
    {
        $this->users->update($user, ['password' => $password]);

        // Sign out the user's other devices, but not the one making the
        // change — unlike resetPassword(), which revokes everything.
        $this->users->revokeOtherTokens($user);
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

        $this->users->update($user, [
            'otp' => $this->generateOtp(),
            'otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

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

        $this->users->update($user, [
            'password' => $password,
            'otp' => $this->consumedOtpPlaceholder(),
            'otp_expires_at' => null,
        ]);

        // Invalidate existing sessions since the password just changed.
        $this->users->revokeAllTokens($user);
    }

    /**
     * Revoke the token used to make the current request.
     */
    public function logout(User $user): void
    {
        $this->users->revokeCurrentToken($user);
    }

    private function findByEmailAndRole(string $email, string $role): ?User
    {
        return $this->users->findByEmailAndRole(Str::lower($email), $role);
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
