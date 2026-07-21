<?php

namespace App\Repositories\Cms;

use App\Models\MealCategory;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Every Eloquent query against the meal_categories table.
 *
 * @extends BaseRepository<MealCategory>
 */
class MealCategoryRepository extends BaseRepository
{
    protected function model(): string
    {
        return MealCategory::class;
    }

    /**
     * Only the categories the public "meal types" grid should show.
     *
     * @return Collection<int, MealCategory>
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
