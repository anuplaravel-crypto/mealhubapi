<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesImageUrl;
use App\Models\HomeSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One home-page section's envelope, with its published features nested.
 *
 * The heading arrives split: `heading` is the plain lead and `heading_accent`
 * the highlighted tail, which the client renders in the accent colour and
 * joins back into one sentence.
 *
 * `extras` is the section's own handful of properties (button labels, store
 * links, the about badge) — see HomeSection::EXTRA_FIELDS for which keys each
 * section understands. It is always an object, never null, so a client can
 * read a missing key rather than guard the container first. One value needs
 * care: the about badge's text may contain a real newline, and that break is
 * meaningful — render it, don't collapse it.
 *
 * @mixin HomeSection
 */
class HomeSectionResource extends JsonResource
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
            'key' => $this->key,
            'eyebrow' => $this->eyebrow,
            'heading' => $this->heading,
            'heading_accent' => $this->heading_accent,
            'body' => $this->body,
            'image_url' => $this->resolveImageUrl(HomeSection::IMAGE_COLLECTION, $this->image, $this->image_url, 'large'),
            'extras' => (object) ($this->extras ?? []),
            'features' => SectionFeatureResource::collection($this->whenLoaded('features')),
        ];
    }
}
