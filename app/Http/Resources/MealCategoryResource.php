<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesImageUrl;
use App\Models\MealCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One meal-type browse card.
 *
 * A null `image_url` means the card has neither an upload nor an external
 * photo, and the client must omit the image element rather than render an
 * empty `src`.
 *
 * @mixin MealCategory
 */
class MealCategoryResource extends JsonResource
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
            'tagline' => $this->tagline,
            'image_url' => $this->resolveImageUrl(MealCategory::IMAGE_COLLECTION, $this->image, $this->image_url),
            'sort_order' => $this->sort_order,
        ];
    }
}
