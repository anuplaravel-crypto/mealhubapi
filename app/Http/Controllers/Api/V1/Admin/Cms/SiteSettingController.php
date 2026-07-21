<?php

namespace App\Http\Controllers\Api\V1\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\UpdateSiteSettingRequest;
use App\Http\Resources\Admin\Cms\AdminSiteSettingResource;
use App\Http\Traits\ApiResponse;
use App\Services\Cms\SiteSettingService;
use Illuminate\Http\JsonResponse;

/**
 * The admin surface over site-wide branding.
 *
 * Deliberately not a {@see BaseCmsController}: there is one row, so there is no
 * list to order, nothing to create or delete, and no publish flag to toggle.
 * Inheriting five actions in order to route only two of them would advertise a
 * shape this resource does not have.
 *
 * `update` is POST rather than PUT because it carries the logo upload, and PHP
 * populates no uploaded-file bag on a PUT body — the same constraint the
 * testimonial edit, the rider vehicle upsert and the restaurant document upsert
 * all answer the same way.
 *
 * The read is the same one the anonymous home payload makes, so a database that
 * has never been saved answers with the branding the site shipped with rather
 * than nulls. `is_persisted` on the Resource is how the client tells that
 * fallback from a real row; this endpoint, unlike the public one, is allowed to
 * turn it into a row.
 */
class SiteSettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SiteSettingService $siteSettings,
    ) {}

    public function show(): JsonResponse
    {
        return $this->successResponse(
            new AdminSiteSettingResource($this->siteSettings->current()),
        );
    }

    public function update(UpdateSiteSettingRequest $request): JsonResponse
    {
        $settings = $this->siteSettings->update(
            $request->safe()->except('logo'),
            $request->file('logo'),
        );

        return $this->successResponse(
            new AdminSiteSettingResource($settings),
            'Site settings updated.',
        );
    }
}
