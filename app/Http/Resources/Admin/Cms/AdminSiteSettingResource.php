<?php

namespace App\Http\Resources\Admin\Cms;

use App\Http\Resources\SiteSettingResource;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Site-wide branding as its editor sees it.
 *
 * Spreads the public {@see SiteSettingResource} so the shared fields cannot
 * drift, and adds the two an editor needs and a visitor does not.
 *
 * `is_persisted` is the one that matters. The read falls back to an unsaved
 * instance carrying the branding the site shipped with, so a form rendered
 * against a never-saved database looks exactly like one rendered against a
 * saved one — this flag is how the client can say "these are defaults" rather
 * than implying an admin chose them.
 *
 * There is no `external_logo_url` counterpart to the testimonial resource's:
 * the site's own mark has no hot-linked fallback column, so `logo_url` is
 * either an uploaded file or nothing.
 *
 * @mixin SiteSetting
 */
class AdminSiteSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new SiteSettingResource($this->resource))->toArray($request),
            'has_logo' => $this->logo !== null && $this->logo !== '',
            'is_persisted' => $this->exists,
            'updated_at' => $this->updated_at,
        ];
    }
}
