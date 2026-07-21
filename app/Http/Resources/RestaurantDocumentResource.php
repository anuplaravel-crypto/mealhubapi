<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\Media\MediaPlacement;
use App\Services\RestaurantDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a restaurant has on file, as its owner sees it.
 *
 * The underlying model is the `User` row — the two documents are columns on it,
 * not a table of their own — but this Resource deliberately exposes *only* the
 * paperwork. A client asking "is my account ready for review?" gets that answer
 * and nothing else; `UserResource` is still the account view.
 *
 * No filename and no path is emitted for a filed document. These are identity
 * papers on a private disk: what ships is the streaming endpoint's address, the
 * kind of file behind it, and whether the slot is filled at all.
 *
 * @mixin User
 */
class RestaurantDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // The gate an admin's approval queue waits on, computed here rather
            // than left to the client to derive from two booleans.
            'is_complete' => collect(RestaurantDocumentService::SLOTS)
                ->every(fn (array $slot): bool => filled($this->{$slot['column']})),
            'documents' => array_map(
                fn (int $number): array => $this->describeSlot($request, $number),
                array_keys(RestaurantDocumentService::SLOTS),
            ),
        ];
    }

    /**
     * One slot's public description.
     *
     * `url` is the endpoint that streams the file, or null when the slot is
     * empty — or when the resource describes somebody other than the caller and
     * the caller is not an admin, since that endpoint would refuse them anyway.
     * Append `?variant=` (`small`, `medium`, `large`, `original`) to pick a
     * size; a PDF ignores it and always serves the original.
     *
     * @return array<string, mixed>
     */
    private function describeSlot(Request $request, int $number): array
    {
        $slot = RestaurantDocumentService::SLOTS[$number];
        $filename = $this->{$slot['column']};

        return [
            'slot' => $number,
            'key' => $slot['key'],
            'label' => $slot['label'],
            'on_file' => filled($filename),
            'is_pdf' => MediaPlacement::isPassthrough($filename),
            'url' => filled($filename) ? $this->slotUrl($request, $number) : null,
        ];
    }

    /**
     * The address the caller should read this slot through: their own endpoint
     * when the documents are theirs, the admin one when they are an admin
     * reading somebody else's.
     */
    private function slotUrl(Request $request, int $number): ?string
    {
        $caller = $request->user();

        if ($caller?->is($this->resource) === true) {
            return route('api.v1.restaurant.documents.download', ['slot' => $number]);
        }

        return $caller?->role === 'admin'
            ? route('api.v1.admin.restaurants.documents.show', ['restaurant' => $this->id, 'slot' => $number])
            : null;
    }
}
