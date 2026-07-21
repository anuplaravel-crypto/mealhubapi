<?php

namespace App\Repositories\Cms;

use App\Models\HomeStat;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Every Eloquent query against the home_stats table.
 *
 * @extends BaseRepository<HomeStat>
 */
class HomeStatRepository extends BaseRepository
{
    protected function model(): string
    {
        return HomeStat::class;
    }

    /**
     * Every published stat, keyed by placement, each group in display order.
     *
     * Fetched as one query and grouped in PHP rather than one query per
     * placement: the home payload needs both groups on every request, and the
     * table holds a handful of rows.
     *
     * Placements with no published rows are absent — the caller fills them in.
     *
     * @return SupportCollection<string, Collection<int, HomeStat>>
     */
    public function publishedByPlacement(): SupportCollection
    {
        return $this->query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('placement');
    }
}
