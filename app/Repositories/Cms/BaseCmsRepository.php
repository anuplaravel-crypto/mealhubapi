<?php

namespace App\Repositories\Cms;

use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared queries for the CMS tables an admin reorders.
 *
 * Every editorial table but two carries `sort_order` and needs the same two
 * things on top of {@see BaseRepository}: the complete list in display order,
 * and the position a newly added row should take. Both are here rather than
 * copy-pasted into eight classes — the reference app repeated `nextSortOrder()`
 * verbatim in six of them, differing only in which column scoped it.
 *
 * Two tables deliberately stay on {@see BaseRepository} instead:
 * `site_settings` is a single row with nothing to order, and `home_sections` is
 * a fixed set ordered by `key` with no `sort_order` column at all.
 *
 * @template TModel of Model
 *
 * @extends BaseRepository<TModel>
 */
abstract class BaseCmsRepository extends BaseRepository
{
    /**
     * The column that partitions ordering, when rows are ordered within groups
     * rather than across the whole table — `placement` on home stats,
     * `location` on nav menus, `home_section_id` on section features.
     *
     * Null means one continuous order for the table, which is the common case.
     */
    protected function groupColumn(): ?string
    {
        return null;
    }

    /**
     * Every row in display order, for the admin list.
     *
     * Grouped rows lead with their group so the two placements or four
     * locations stay together, then follow the same `sort_order`, `id` tiebreak
     * the public reads use.
     *
     * @return Collection<int, TModel>
     */
    public function allOrdered(): Collection
    {
        $query = $this->query();

        if ($this->groupColumn() !== null) {
            $query->orderBy($this->groupColumn());
        }

        return $query->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Position for a row added without an explicit order, so new entries land
     * at the end rather than sharing position 0.
     *
     * Scoped to the group when there is one: a new hero stat should follow the
     * last hero stat, not the highest position in the table.
     */
    public function nextSortOrder(int|string|null $group = null): int
    {
        $query = $this->query();

        if ($this->groupColumn() !== null && $group !== null) {
            $query->where($this->groupColumn(), $group);
        }

        return (int) $query->max('sort_order') + 1;
    }
}
