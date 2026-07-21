<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesImageUrl;
use App\Models\FeaturedRestaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One card in the featured-restaurants carousel.
 *
 * `user_id` is not exposed. It is the seam where a real restaurant entity will
 * eventually take over, and is null for every card today — publishing an
 * internal account id on an anonymous endpoint buys the client nothing.
 *
 * `rating` ships as a one-decimal string from the model's cast; `tag`,
 * `perk_label` and `image_url` are all nullable, and a null means the client
 * omits that element rather than rendering it empty.
 *
 * @mixin FeaturedRestaurant
 */
class FeaturedRestaurantResource extends JsonResource
{
    use ResolvesImageUrl;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image_url' => $this->resolveImageUrl(FeaturedRestaurant::IMAGE_COLLECTION, $this->image, $this->image_url),
            'rating' => $this->rating,
            'location' => $this->location,
            'cuisines' => $this->cuisines,
            'delivery_time' => $this->delivery_time,
            'tag' => $this->tag,
            'perk_label' => $this->perk_label,
            'perk_variant' => $this->perk_variant,
            'sort_order' => $this->sort_order,
        ];
    }
}
