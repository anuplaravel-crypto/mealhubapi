<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One user account, as its owner sees it.
 *
 * Two fields need explaining:
 *
 * - `image_url` is **not** a storage URL the way every CMS resource's is.
 *   Profile pictures are personal data on the private disk, so what ships is
 *   the authenticated streaming endpoint's address — and only when the resource
 *   *is* the caller, since that endpoint can serve nobody else's file. Phase 11
 *   gets its own `AdminUserResource` for the admin's view of other people.
 * - `country` / `county` / `city` are the hydrated rows behind the three ids,
 *   present only where the caller was given them eager-loaded (the profile
 *   endpoints). Everywhere else — registration, login — the key is `null` and
 *   the ids are the answer; the alternative, resolving them in the Resource,
 *   is three extra queries per user on every list that later reuses this.
 *
 * @mixin User
 */
class UserResource extends JsonResource
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
            'image' => $this->image,
            'image_url' => $this->profilePictureUrl($request),
            'role' => $this->role,
            'status' => $this->status,
            'is_email_verified' => $this->is_email_verified,
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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Address of the endpoint that streams this user's picture, or null when
     * there is no picture — or when this resource is somebody other than the
     * caller, whose file that endpoint would refuse to serve anyway.
     *
     * A null means "render no avatar": clients must not emit an empty `src`,
     * which browsers resolve against the current document. Append `?variant=`
     * (`small`, `medium`, `large`, `original`) to pick a size; the endpoint
     * falls back to `medium`.
     */
    private function profilePictureUrl(Request $request): ?string
    {
        if ($this->image === null || $this->image === '') {
            return null;
        }

        return $request->user()?->is($this->resource) === true
            ? route('api.v1.media.profile-picture')
            : null;
    }
}
