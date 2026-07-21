<?php

namespace App\Http\Controllers\Api\V1\Admin\Cms;

use App\Http\Requests\Cms\SaveTestimonialRequest;
use App\Http\Resources\Admin\Cms\AdminTestimonialResource;
use App\Services\Cms\BaseCmsService;
use App\Services\Cms\TestimonialService;
use Illuminate\Http\JsonResponse;

/**
 * The admin surface over the home-page review carousel.
 *
 * The first concrete {@see BaseCmsController}, and the pattern-setter for the
 * six reorderable collections that follow: `index`, `toggle` and `destroy` are
 * inherited untouched, and the two write actions exist only to name the Form
 * Request and pull the upload out of the payload.
 *
 * Both writes are POST. `store` is POST because it creates; `update` is POST
 * rather than PUT because it carries a file, and PHP populates no uploaded-file
 * bag on a PUT body — the same reason the rider vehicle and restaurant document
 * upserts are POST. `toggle` is a PATCH of one field and `destroy` a real
 * DELETE, so the reference app's `POST .../{id}/delete` Blade workaround does
 * not survive the port.
 */
class TestimonialController extends BaseCmsController
{
    public function __construct(
        private readonly TestimonialService $testimonials,
    ) {}

    protected function service(): BaseCmsService
    {
        return $this->testimonials;
    }

    protected function resource(): string
    {
        return AdminTestimonialResource::class;
    }

    protected function label(): string
    {
        return 'Testimonial';
    }

    public function store(SaveTestimonialRequest $request): JsonResponse
    {
        return $this->completeStore(
            $request->safe()->except('avatar'),
            $request->file('avatar'),
        );
    }

    public function update(SaveTestimonialRequest $request, int $id): JsonResponse
    {
        return $this->completeUpdate(
            $id,
            $request->safe()->except('avatar'),
            $request->file('avatar'),
        );
    }
}
