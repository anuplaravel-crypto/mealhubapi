<?php

namespace App\Repositories\Cms;

use App\Models\NavMenu;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Every Eloquent query against the nav_menus table.
 *
 * @extends BaseRepository<NavMenu>
 */
class NavMenuRepository extends BaseRepository
{
    protected function model(): string
    {
        return NavMenu::class;
    }

    /**
     * Every published link, keyed by location.
     *
     * One query rather than four: the table holds a couple of dozen rows, so
     * grouping in PHP is free at this size and the home payload stays flat in
     * its query count.
     *
     * Locations with no published rows are absent from the result — the caller
     * is responsible for filling them in, since the client needs all four keys
     * present to iterate over.
     *
     * @return SupportCollection<string, Collection<int, NavMenu>>
     */
    public function publishedGrouped(): SupportCollection
    {
        return $this->query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('location');
    }
}
