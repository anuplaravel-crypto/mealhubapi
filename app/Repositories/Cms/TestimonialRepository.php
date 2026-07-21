<?php

namespace App\Repositories\Cms;

use App\Models\Testimonial;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Every Eloquent query against the testimonials table.
 *
 * @extends BaseRepository<Testimonial>
 */
class TestimonialRepository extends BaseRepository
{
    protected function model(): string
    {
        return Testimonial::class;
    }

    /**
     * Only the testimonials the public carousel should show.
     *
     * @return Collection<int, Testimonial>
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
