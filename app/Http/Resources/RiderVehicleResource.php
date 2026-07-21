<?php

namespace App\Http\Resources;

use App\Models\RiderVehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One rider's vehicle, as its owner sees it.
 *
 * `image_url` is an endpoint address rather than a storage URL, for the same
 * reason `UserResource`'s is: a photo of a named person's registration plate is
 * personal data on the private disk, and the streaming endpoint serves nobody
 * but the token holder. This is also why the shared `ResolvesImageUrl` trait is
 * not used — that trait is hard-wired to `MediaPlacement::Cms` because a public
 * URL is the only thing it can build.
 *
 * `rider_id` is deliberately absent: the only caller is the rider themselves,
 * who is not told their own id twice. Phase 11's admin view of a rider is where
 * the owner becomes worth naming.
 *
 * @mixin RiderVehicle
 */
class RiderVehicleResource extends JsonResource
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
            'vehicle_type' => $this->vehicle_type,
            'registration_number' => $this->registration_number,
            'vehicle_brand' => $this->vehicle_brand,
            'vehicle_model' => $this->vehicle_model,
            'vehicle_color' => $this->vehicle_color,
            'image' => $this->image,
            'image_url' => $this->vehicleImageUrl($request),
            // Read-only on this surface: it mirrors the rider's account status,
            // which only an admin can flip.
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Address of the endpoint that streams this vehicle's photo, or null when
     * there is none — or when the resource belongs to somebody other than the
     * caller, whose file that endpoint would refuse to serve anyway.
     *
     * A null means "render no photo": clients must not emit an empty `src`,
     * which browsers resolve against the current document. Append `?variant=`
     * (`small`, `medium`, `large`, `original`) to pick a size; the endpoint
     * falls back to `medium`.
     */
    private function vehicleImageUrl(Request $request): ?string
    {
        if ($this->image === null || $this->image === '') {
            return null;
        }

        return (string) $request->user()?->getKey() === (string) $this->rider_id
            ? route('api.v1.rider.vehicle.image')
            : null;
    }
}
