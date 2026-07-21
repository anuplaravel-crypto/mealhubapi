<?php

namespace App\Http\Resources;

use App\Models\NavMenu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One navigation link.
 *
 * `location` is not repeated in the row — the payload already groups links by
 * it. `route_key` and `url` are mutually exclusive targets and both ship raw:
 * `route_key` is an opaque token the SPA maps to its own path (it is *not* a
 * Laravel route name, see the NavMenu model), and choosing between the two is
 * the client's job. `icon_class` and `variant` are likewise bare tokens, not
 * CSS.
 *
 * @mixin NavMenu
 */
class NavMenuResource extends JsonResource
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
            'group_label' => $this->group_label,
            'label' => $this->label,
            'icon_class' => $this->icon_class,
            'variant' => $this->variant,
            'url' => $this->url,
            'route_key' => $this->route_key,
            'sort_order' => $this->sort_order,
        ];
    }
}
