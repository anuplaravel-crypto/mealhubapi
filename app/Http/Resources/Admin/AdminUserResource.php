<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\CityResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\CountyResource;
use App\Http\Resources\RestaurantDocumentResource;
use App\Http\Resources\RiderVehicleResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One account, as an admin reviewing it sees it.
 *
 * A separate class from {@see UserResource} rather than a flag on it, because
 * the two answer different questions. `UserResource` answers "what is my
 * account?" for its owner. This answers "should I activate this account?" for
 * somebody else — so it carries the verification material the owner's own view
 * has no reason to repeat, and drops the things only an owner should see.
 *
 * Three deliberate differences:
 *
 * - **No `image` and no `image_url`.** A profile picture is a private file, and
 *   `v1/media/profile-picture` serves the token holder's own and nobody else's,
 *   so an admin has no address to be given. Emitting the stored filename
 *   instead would put a private storage name in a response — the one thing the
 *   media rules forbid outright. `has_profile_picture` is the whole of what an
 *   admin can act on.
 * - **The verification material is role-conditional.** A restaurant carries its
 *   `documents`, a rider its `vehicle`, and neither key appears on a role that
 *   has no such thing — an absent key is clearer than a null that a client has
 *   to know is structural.
 * - **`accept_registration_tnc` and `marketing_consent` are present.** They are
 *   the record of what the person agreed to at signup, which is exactly the
 *   sort of thing an account review is for.
 *
 * The nested Resources are reused rather than re-implemented, and both already
 * know how to answer an admin caller: `RestaurantDocumentResource` emits the
 * admin download address, `RiderVehicleResource` the admin photo address.
 *
 * @mixin User
 */
class AdminUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'preferred_language' => $this->preferred_language,
            'role' => $this->role,

            // The two flags an admin acts on: the activation gate they own, and
            // the email verification the user completed (or did not) themselves.
            'status' => (bool) $this->status,
            'is_email_verified' => (bool) $this->is_email_verified,

            'has_profile_picture' => filled($this->image),

            'accept_registration_tnc' => (bool) $this->accept_registration_tnc,
            'marketing_consent' => (bool) $this->marketing_consent,

            'address1' => $this->address1,
            'address2' => $this->address2,
            'zip_code' => $this->zip_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'country_id' => $this->country_id,
            'county_id' => $this->county_id,
            'city_id' => $this->city_id,
            'country' => $this->whenLoaded('country', fn () => new CountryResource($this->country), null),
            'county' => $this->whenLoaded('county', fn () => new CountyResource($this->county), null),
            'city' => $this->whenLoaded('city', fn () => new CityResource($this->city), null),

            $this->mergeWhen($this->role === 'restaurant', fn (): array => [
                'documents' => new RestaurantDocumentResource($this->resource),
            ]),

            $this->mergeWhen($this->role === 'rider', fn (): array => [
                'vehicle' => $this->vehicle(),
            ]),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The rider's vehicle, or null before they have registered one.
     *
     * Read off the eager-loaded relation rather than queried, so a page of
     * riders costs one extra query in total instead of one per row. Newest
     * wins for the same reason `RiderVehicleRepository::forRider()` applies
     * `latest()`: the relation is a `hasMany` and nothing at the database level
     * stops a second row existing.
     */
    private function vehicle(): ?RiderVehicleResource
    {
        if (! $this->resource->relationLoaded('vehicles')) {
            return null;
        }

        $vehicle = $this->vehicles->sortByDesc('id')->first();

        return $vehicle === null ? null : new RiderVehicleResource($vehicle);
    }
}
