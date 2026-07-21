<?php

namespace App\Http\Resources;

use App\Models\SectionFeature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One repeatable item inside a home section — a how-it-works step, an about
 * feature, or a rider perk.
 *
 * A step's number is deliberately absent: it is the item's position in this
 * list, so hiding a step renumbers the rest rather than leaving a gap at 2.
 *
 * `home_section_id` is not exposed — features only ever arrive nested inside
 * their section.
 *
 * @mixin SectionFeature
 */
class SectionFeatureResource extends JsonResource
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
            'title' => $this->title,
            'body' => $this->body,
            'icon_class' => $this->icon_class,
            'accent' => $this->accent,
            'sort_order' => $this->sort_order,
        ];
    }
}
