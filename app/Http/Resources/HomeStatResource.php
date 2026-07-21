<?php

namespace App\Http\Resources;

use App\Models\HomeStat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One number on the home page.
 *
 * `placement` is not repeated in the row — the payload already splits the two
 * groups. `value` is a string on purpose and the two placements treat it
 * differently: a hero stat renders it verbatim, suffix and all ("15k+"), while
 * a stat-bar value is digits only and feeds a count-up animation.
 *
 * `accent` is a bare token ("green"), not a CSS class.
 *
 * @mixin HomeStat
 */
class HomeStatResource extends JsonResource
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
            'label' => $this->label,
            'value' => $this->value,
            'icon_class' => $this->icon_class,
            'accent' => $this->accent,
            'sort_order' => $this->sort_order,
        ];
    }
}
