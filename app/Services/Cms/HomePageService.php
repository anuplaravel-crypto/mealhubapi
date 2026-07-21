<?php

namespace App\Services\Cms;

use App\Models\FeaturedRestaurant;
use App\Models\HomeSection;
use App\Models\HomeStat;
use App\Models\MealCategory;
use App\Models\NavMenu;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Repositories\Cms\FeaturedRestaurantRepository;
use App\Repositories\Cms\HomeSectionRepository;
use App\Repositories\Cms\HomeStatRepository;
use App\Repositories\Cms\MealCategoryRepository;
use App\Repositories\Cms\NavMenuRepository;
use App\Repositories\Cms\SiteSettingRepository;
use App\Repositories\Cms\TestimonialRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Assembles everything the public home page renders, in one payload.
 *
 * MealHub split this in two — a HomePageService for the page's own content and
 * a view composer for site-wide branding and navigation, because those also
 * reached the auth pages. A cross-origin SPA has no shared layout to compose
 * into, so the split would just mean two round trips for one screen; branding
 * and navigation are folded in here instead.
 *
 * Every group the client iterates over is guaranteed present, even when empty:
 * an absent key is `undefined` in JavaScript and would throw on `.map()`,
 * whereas an empty array renders nothing. `sections` is the exception — it is
 * looked up by key rather than iterated, so an unpublished section is simply
 * missing.
 */
class HomePageService
{
    public function __construct(
        private readonly SiteSettingRepository $siteSettings,
        private readonly NavMenuRepository $navMenus,
        private readonly HomeStatRepository $stats,
        private readonly HomeSectionRepository $sections,
        private readonly MealCategoryRepository $mealCategories,
        private readonly FeaturedRestaurantRepository $featuredRestaurants,
        private readonly TestimonialRepository $testimonials,
    ) {}

    /**
     * `mealCategories` and `featuredRestaurants` are queried separately from
     * `sections` even though each backs a section's grid — see those tables'
     * migrations for why they carry no relationship to home_sections at all.
     *
     * The featured restaurants arrive as one flat ordered list, not chunked
     * into carousel slides the way MealHub's service returned them: how many
     * cards make a slide is a DOM decision the client owns.
     *
     * @return array{
     *     site: SiteSetting,
     *     navMenus: SupportCollection<string, Collection<int, NavMenu>>,
     *     heroStats: Collection<int, HomeStat>,
     *     statBarStats: Collection<int, HomeStat>,
     *     sections: Collection<string, HomeSection>,
     *     mealCategories: Collection<int, MealCategory>,
     *     featuredRestaurants: Collection<int, FeaturedRestaurant>,
     *     testimonials: Collection<int, Testimonial>
     * }
     */
    public function homeData(): array
    {
        $stats = $this->stats->publishedByPlacement();

        return [
            'site' => $this->siteSettings->current(),
            'navMenus' => $this->menusByLocation(),
            'heroStats' => $stats->get(HomeStat::PLACEMENT_HERO, new Collection),
            'statBarStats' => $stats->get(HomeStat::PLACEMENT_STAT_BAR, new Collection),
            'sections' => $this->sections->publishedKeyed(),
            'mealCategories' => $this->mealCategories->published(),
            'featuredRestaurants' => $this->featuredRestaurants->published(),
            'testimonials' => $this->testimonials->published(),
        ];
    }

    /**
     * The published links, with every location present.
     *
     * groupBy() omits a location that has no published rows, so a client
     * iterating the social row would hit an undefined key on a site that has
     * not set any social links up yet.
     *
     * @return SupportCollection<string, Collection<int, NavMenu>>
     */
    private function menusByLocation(): SupportCollection
    {
        $grouped = $this->navMenus->publishedGrouped();

        /** @var SupportCollection<string, Collection<int, NavMenu>> $empty */
        $empty = SupportCollection::make(NavMenu::LOCATIONS)
            ->mapWithKeys(fn (string $location) => [$location => new Collection]);

        return $empty->merge($grouped);
    }
}
