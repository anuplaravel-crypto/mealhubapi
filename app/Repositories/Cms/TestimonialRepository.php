<?php

namespace App\Repositories\Cms;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Collection;

/**
 * Every Eloquent query against the testimonials table.
 *
 * @extends BaseCmsRepository<Testimonial>
 */
class TestimonialRepository extends BaseCmsRepository
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
