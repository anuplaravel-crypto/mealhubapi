<?php

namespace App\Repositories\Cms;

use App\Models\SiteSetting;
use App\Repositories\BaseRepository;

/**
 * Every Eloquent query against the single-row site_settings table.
 *
 * @extends BaseRepository<SiteSetting>
 */
class SiteSettingRepository extends BaseRepository
{
    protected function model(): string
    {
        return SiteSetting::class;
    }

    /**
     * The settings row, or an unsaved instance carrying the defaults when the
     * table has never been seeded.
     *
     * Deliberately **not** a `firstOrCreate`, unlike MealHub's version: the
     * only caller today is an anonymous public GET, and a read endpoint that
     * writes on first hit is a surprise in an API. The defaults still matter —
     * a freshly migrated database answers `/api/v1/home` with the site's own
     * branding rather than nulls. {@see self::persist()} is where the row is
     * actually written, and only an admin's save reaches it.
     */
    public function current(): SiteSetting
    {
        return $this->find(SiteSetting::SINGLETON_ID)
            ?? $this->model()::make(self::defaults());
    }

    /**
     * Write the settings, creating the singleton row on the first save.
     *
     * The one path that may insert into this table, which is what keeps it a
     * singleton — there is deliberately no `create()` caller and no store
     * endpoint. The key is force-filled rather than mass-assigned because `id`
     * is not fillable: without pinning it, a first save on a table whose
     * auto-increment has already advanced would create a row
     * {@see self::current()} could never find again.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function persist(array $attributes): SiteSetting
    {
        $settings = $this->find(SiteSetting::SINGLETON_ID)
            ?? $this->model()::make()->forceFill(['id' => SiteSetting::SINGLETON_ID]);

        $settings->fill($attributes)->save();

        return $settings;
    }

    /**
     * The branding the site ships with, used when no row exists yet.
     *
     * Kept identical to SiteSettingSeeder so seeded and unseeded databases
     * answer with the same payload.
     *
     * @return array<string, string|null>
     */
    private static function defaults(): array
    {
        return [
            'site_name' => 'MealHub',
            'brand_primary_text' => 'Meal',
            'brand_accent_text' => 'Hub',
            'meta_title' => 'MealHub — Fresh Meals, Delivered with Care',
            'meta_description' => 'Personalised meal plans from trusted local restaurants, delivered fresh by friendly riders.',
            'logo' => null,
            'footer_blurb' => 'Personalised meal plans from trusted local restaurants, delivered fresh by friendly riders. Eat better, live better.',
        ];
    }
}
