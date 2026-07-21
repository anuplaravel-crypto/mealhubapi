<?php

namespace App\Http\Resources;

use App\Models\County;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin County
 */
class CountyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The parent id is exposed so a client holding a stored county can restore
     * the cascade's country step without a second lookup.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country_id' => $this->country_id,
        ];
    }
}
