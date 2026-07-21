<?php

namespace App\Repositories\Cms;

use App\Models\HomeSection;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Every Eloquent query against the home_sections table.
 *
 * Section features are read through this repository's eager load rather than
 * their own class: the public payload and the admin list both reach them via
 * the parent section, so a SectionFeatureRepository would hold nothing until
 * Phase 10 adds the write paths.
 *
 * @extends BaseRepository<HomeSection>
 */
class HomeSectionRepository extends BaseRepository
{
    protected function model(): string
    {
        return HomeSection::class;
    }

    /**
     * The published sections, keyed by section key, each carrying only its
     * published features in display order.
     *
     * Keyed so a client can ask for one section by name; eager-loaded so six
     * sections stay two queries rather than one per section.
     *
     * @return Collection<string, HomeSection>
     */
    public function publishedKeyed(): Collection
    {
        return $this->query()
            ->with(['features' => function ($query) {
                $query->where('is_published', true)->orderBy('sort_order')->orderBy('id');
            }])
            ->where('is_published', true)
            ->get()
            ->keyBy('key');
    }
}
