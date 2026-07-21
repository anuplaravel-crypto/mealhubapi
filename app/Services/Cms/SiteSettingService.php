<?php

namespace App\Services\Cms;

use App\Models\SiteSetting;
use App\Repositories\Cms\SiteSettingRepository;
use App\Services\Media\ImageUploadService;
use App\Services\Media\MediaPlacement;
use Illuminate\Http\UploadedFile;

/**
 * Business rules around the single site_settings row.
 *
 * Deliberately not a {@see BaseCmsService}: the table is a singleton, so there
 * is no list to order, nothing to create or delete, and no publish flag to
 * toggle. Inheriting five methods to hide four of them would describe the
 * resource wrongly.
 *
 * The read is shared with the anonymous home payload, which is why it returns
 * an unsaved instance carrying the shipped branding when nothing has been saved
 * yet — see {@see SiteSettingRepository::current()}. This service is the only
 * thing that ever turns that instance into a row.
 */
class SiteSettingService
{
    public function __construct(
        private readonly SiteSettingRepository $settings,
        private readonly ImageUploadService $images,
    ) {}

    /**
     * The current settings, real or defaulted.
     */
    public function current(): SiteSetting
    {
        return $this->settings->current();
    }

    /**
     * Save the settings, replacing the logo when a new file is supplied.
     *
     * There is no `logo_url` companion column — unlike every other CMS image,
     * the site's own mark is never hot-linked from somewhere else — so nothing
     * has to be cleared alongside the upload.
     *
     * The superseded logo's variants are removed by the upload service once the
     * new ones are safely written, so a failure mid-encode cannot leave the
     * site pointing at a file that no longer exists.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(array $data, ?UploadedFile $logo = null): SiteSetting
    {
        if ($logo !== null) {
            $data['logo'] = $this->images->store(
                MediaPlacement::Cms,
                SiteSetting::IMAGE_COLLECTION,
                $logo,
                replacing: $this->settings->current()->logo,
            );
        }

        return $this->settings->persist($data);
    }
}
