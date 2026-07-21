<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesImageUrl;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One customer review.
 *
 * `rating` ships as a one-decimal string and the star row is the client's to
 * render — with one rule the existing reviews depend on: draw no empty stars.
 * A 4.5 shows five icons (four full plus a half), a 3.0 shows three.
 *
 * A null `avatar_url` means the review has neither an upload nor an external
 * photo; omit the image rather than render an empty `src`.
 *
 * @mixin Testimonial
 */
class TestimonialResource extends JsonResource
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
            'quote' => $this->quote,
            'author_name' => $this->author_name,
            'author_role' => $this->author_role,
            'avatar_url' => $this->resolveImageUrl(Testimonial::IMAGE_COLLECTION, $this->avatar, $this->avatar_url, 'small'),
            'rating' => $this->rating,
            'sort_order' => $this->sort_order,
        ];
    }
}
