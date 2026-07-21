<?php

namespace App\Repositories\Cms;

use App\Models\FeaturedRestaurant;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Every Eloquent query against the featured_restaurants table.
 *
 * The linked `restaurant` relation is deliberately not eager-loaded here: the
 * public payload exposes none of it, so loading it would be a query the
 * response never uses. Phase 11's admin list is where that changes.
 *
 * @extends BaseRepository<FeaturedRestaurant>
 */
class FeaturedRestaurantRepository extends BaseRepository
{
    protected function model(): string
    {
        return FeaturedRestaurant::class;
    }

    /**
     * Only the cards the public carousel should show.
     *
     * @return Collection<int, FeaturedRestaurant>
     */
    public function published(): Collection
    {
        return $this->query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
