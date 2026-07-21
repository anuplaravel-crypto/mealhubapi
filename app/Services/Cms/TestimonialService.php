<?php

namespace App\Services\Cms;

use App\Models\Testimonial;
use App\Repositories\Cms\TestimonialRepository;
use App\Services\Media\ImageUploadService;

/**
 * Business rules around the home-page testimonial carousel.
 *
 * The first concrete {@see BaseCmsService} and the pattern-setter for the six
 * reorderable collections that follow: everything this class needs is already
 * in the base, so all it declares is where its avatars are stored and which two
 * columns hold them.
 *
 * @extends BaseCmsService<Testimonial>
 */
class TestimonialService extends BaseCmsService
{
    public function __construct(TestimonialRepository $testimonials, ImageUploadService $images)
    {
        parent::__construct($testimonials, $images);
    }

    protected function imageCollection(): ?string
    {
        return Testimonial::IMAGE_COLLECTION;
    }

    /**
     * A review's photo is its author's face, so the columns are named `avatar`
     * and `avatar_url` rather than the `image` pair the rest of the CMS uses.
     */
    protected function imageField(): string
    {
        return 'avatar';
    }

    protected function imageUrlField(): ?string
    {
        return 'avatar_url';
    }
}
