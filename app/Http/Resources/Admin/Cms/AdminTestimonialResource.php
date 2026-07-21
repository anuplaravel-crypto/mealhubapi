<?php

namespace App\Http\Resources\Admin\Cms;

use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One testimonial as its editor sees it.
 *
 * The public {@see TestimonialResource} is spread rather than restated, so the
 * two can never disagree about the fields they share; this class only appends
 * what an anonymous visitor has no business reading.
 *
 * Three of those additions answer questions the public shape deliberately
 * collapses:
 *
 * - `is_published` is the whole point of the admin list — an unpublished review
 *   is absent from the public payload entirely, so nothing there could report
 *   it.
 * - `avatar_url` is the *resolved* address, upload beating link. An editor also
 *   needs the raw link back to put it in a form field, which is
 *   `external_avatar_url`, and needs to know an upload is what is currently
 *   winning, which is `has_uploaded_avatar`.
 *
 * @mixin Testimonial
 */
class AdminTestimonialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new TestimonialResource($this->resource))->toArray($request),
            'is_published' => $this->is_published,
            'has_uploaded_avatar' => $this->avatar !== null && $this->avatar !== '',
            'external_avatar_url' => $this->avatar_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
