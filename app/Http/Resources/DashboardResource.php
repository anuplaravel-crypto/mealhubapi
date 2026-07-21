<?php

namespace App\Http\Resources;

use App\Models\RiderVehicle;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The landing payload for whichever role is holding the token.
 *
 * The only Resource in the codebase whose `$this->resource` is an array rather
 * than a model, because a dashboard is not one: it is a small identity block,
 * a badge count, and one role-shaped section, assembled by
 * {@see DashboardService}. Every model that *does* appear inside
 * it — the rider's vehicle, the restaurant's paperwork — still goes through its
 * own Resource, reused rather than re-implemented.
 *
 * Three shape decisions worth keeping:
 *
 * - **`user` is an identity block, not a profile.** Name, email, the two status
 *   flags and an avatar address: enough to render a header card. Address and
 *   the country/county/city rows are deliberately absent — `GET v1/profile`
 *   owns those, and duplicating them here would mean two endpoints to change
 *   every time the profile grows a field.
 * - **The role section is merged, not nested under a fixed key.** A customer
 *   has no onboarding gate beyond email verification, so no `onboarding` key
 *   appears for them at all. An absent key is a clearer answer than a null a
 *   client has to know is structural — the same call `AdminUserResource` makes
 *   for `documents` and `vehicle`.
 * - **`account_activated` rather than `is_verified`.** A restaurant and a rider
 *   pass two independent gates — an admin flipping `users.status`, and the
 *   email verification that lives on `user` — and one key named "verified"
 *   covering both would be read as whichever the client happened to mean.
 */
class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->user();

        return [
            'role' => $user->role,
            'user' => $this->identity($user),
            'notifications' => [
                'unread_count' => $this->resource['unread_notifications'],
            ],
            ...$this->section($user),
        ];
    }

    /**
     * The header card: who the caller is and the two flags that gate them.
     *
     * `image_url` is the streaming endpoint's address rather than a storage
     * URL, exactly as `UserResource`'s is — a profile picture is personal data
     * on the private disk. It needs no "is this the caller" branch here, unlike
     * `UserResource`, because a dashboard is only ever the caller's own.
     *
     * @return array<string, mixed>
     */
    private function identity(User $user): array
    {
        return [
            'id' => $user->id,
            'firstName' => $user->firstName,
            'lastName' => $user->lastName,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'is_email_verified' => $user->is_email_verified,
            'image_url' => filled($user->image) ? route('api.v1.media.profile-picture') : null,
        ];
    }

    /**
     * The role's own section, or nothing.
     *
     * A customer's dashboard has no fourth key: their only gate is email
     * verification, which every role reports on `user` already, so an
     * `onboarding` block here would restate it under a second name.
     *
     * @return array<string, mixed>
     */
    private function section(User $user): array
    {
        return match ($user->role) {
            'admin' => ['users' => $this->resource['user_counts']],
            'restaurant' => ['onboarding' => [
                'account_activated' => $user->status,
                'documents' => new RestaurantDocumentResource($user),
            ]],
            'rider' => ['onboarding' => [
                'account_activated' => $user->status,
                'vehicle_registered' => $this->vehicle() instanceof RiderVehicle,
                'vehicle' => $this->vehicle() === null ? null : new RiderVehicleResource($this->vehicle()),
            ]],
            default => [],
        };
    }

    /**
     * The account this dashboard belongs to.
     */
    private function user(): User
    {
        return $this->resource['user'];
    }

    /**
     * The rider's vehicle, or null before they have registered one.
     */
    private function vehicle(): ?RiderVehicle
    {
        return $this->resource['vehicle'] ?? null;
    }
}
