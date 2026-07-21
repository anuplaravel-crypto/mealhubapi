<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeaturedRestaurantResource;
use App\Http\Resources\HomeSectionResource;
use App\Http\Resources\HomeStatResource;
use App\Http\Resources\MealCategoryResource;
use App\Http\Resources\NavMenuResource;
use App\Http\Resources\SiteSettingResource;
use App\Http\Resources\TestimonialResource;
use App\Http\Traits\ApiResponse;
use App\Services\Cms\HomePageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * The public home page's whole payload, in one anonymous read.
 *
 * One endpoint rather than eight: the client renders the page as a unit, and
 * eight round trips would put its first paint behind the slowest of them. The
 * CMS tables together hold a few dozen rows, so the response stays small.
 *
 * There is nothing to validate and nothing to authorize — every row here is
 * published marketing content.
 */
class HomeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly HomePageService $homePage,
    ) {}

    public function index(): JsonResponse
    {
        $data = $this->homePage->homeData();

        return $this->successResponse([
            'site' => new SiteSettingResource($data['site']),
            'nav_menus' => $this->keyed($data['navMenus'], fn ($links) => NavMenuResource::collection($links)),
            'hero_stats' => HomeStatResource::collection($data['heroStats']),
            'stat_bar_stats' => HomeStatResource::collection($data['statBarStats']),
            'sections' => $this->keyed($data['sections'], fn ($section) => new HomeSectionResource($section)),
            'meal_categories' => MealCategoryResource::collection($data['mealCategories']),
            'featured_restaurants' => FeaturedRestaurantResource::collection($data['featuredRestaurants']),
            'testimonials' => TestimonialResource::collection($data['testimonials']),
        ]);
    }

    /**
     * Transform a string-keyed collection into a keyed object of resources.
     *
     * `Resource::collection()` on its own would re-index the group as a list
     * and lose the location / section key the client looks rows up by, so the
     * keys are preserved here and the resource applied per entry.
     *
     * Cast to an object so an empty map encodes as `{}` rather than `[]` — a
     * client reading `sections.about` should not have to care whether anything
     * is published.
     *
     * @template TValue
     *
     * @param  Collection<string, TValue>  $keyed
     * @param  callable(TValue): mixed  $transform
     */
    private function keyed(Collection $keyed, callable $transform): object
    {
        return (object) $keyed->map($transform)->all();
    }
}
