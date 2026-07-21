<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesImageUrl;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Site-wide branding.
 *
 * No `id` is exposed: the table is a singleton the client never addresses by
 * key. The brand mark ships as its two halves rather than assembled markup —
 * MealHub wrapped the accent in a `<span class="text-orange">`, which is the
 * client's styling decision now.
 *
 * A null `logo_url` is the normal state, and means "fall back to the wordmark".
 *
 * @mixin SiteSetting
 */
class SiteSettingResource extends JsonResource
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
            'site_name' => $this->site_name,
            'brand_primary_text' => $this->brand_primary_text,
            'brand_accent_text' => $this->brand_accent_text,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'logo_url' => $this->resolveImageUrl(SiteSetting::IMAGE_COLLECTION, $this->logo, variant: 'small'),
            'footer_blurb' => $this->footer_blurb,
        ];
    }
}
