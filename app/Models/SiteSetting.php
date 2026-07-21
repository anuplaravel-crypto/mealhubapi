<?php

namespace App\Models;

use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Site-wide branding, held as a single row.
 *
 * No `logoUrl` accessor: turning the stored filename into an absolute URL is
 * response shaping and belongs in the API Resource.
 */
class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory;

    /**
     * The id of the one and only settings row.
     *
     * The table is a singleton by convention, not by constraint — enforced by
     * the read path being a firstOrCreate on this id and by the API exposing
     * no store or destroy endpoint.
     */
    public const SINGLETON_ID = 1;

    /**
     * Directory the image service stores the logo under. Kept as the
     * storage-layout contract even though no upload endpoint exists yet.
     */
    public const IMAGE_COLLECTION = 'site';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_name',
        'brand_primary_text',
        'brand_accent_text',
        'meta_title',
        'meta_description',
        'logo',
        'footer_blurb',
    ];
}
