# Home CMS (public, read-only)

Everything the marketing home page renders — branding, navigation, the two stat rows, the six content sections, meal categories, featured restaurants, and reviews — served as **one anonymous payload**. It is the read half of the CMS: admins edit these tables, anonymous visitors read them through this endpoint.

**Status:** ✅ Implemented (read). 🚧 No write surface — the admin CRUD over these eight tables is Phase 10 of [the roadmap](../roadmap.md), and no upload endpoint exists yet, so `image` columns are only ever populated by hand today.

## Endpoints

| Action | Endpoint | Auth |
| --- | --- | --- |
| Whole home payload | `GET /api/v1/home` | Public |

**One endpoint, not eight.** The client renders the page as a unit, so eight round trips would put first paint behind the slowest of them. The eight CMS tables together hold a few dozen rows, so the response stays small. Per-resource public reads are worth adding only if the SPA genuinely needs one of these groups on its own.

## Request / Response

- Controller: `App\Http\Controllers\Api\V1\HomeController@index`
- Validated by: **nothing** — the endpoint takes no input at all, so there is no Form Request by design
- Shaped by: `App\Http\Resources\{SiteSetting, NavMenu, HomeStat, HomeSection, SectionFeature, MealCategory, FeaturedRestaurant, Testimonial}Resource`, plus the shared `Resources\Concerns\ResolvesImageUrl` trait
- Business logic: `App\Services\Cms\HomePageService`
- Data access: `App\Repositories\Cms\*Repository` (seven classes, all read-only for now)
- Envelope: the standard `{success, data}` from `App\Http\Traits\ApiResponse` — see [api-conventions.md](api-conventions.md)

`data` carries eight keys:

```json
{
  "success": true,
  "data": {
    "site": { "site_name": "MealHub", "brand_primary_text": "Meal", "brand_accent_text": "Hub", "logo_url": null, "…": "…" },
    "nav_menus": { "navbar": [], "footer_menu": [], "social": [], "legal": [] },
    "hero_stats": [{ "id": 1, "label": "Restaurants", "value": "250+", "icon_class": null, "accent": "green", "sort_order": 1 }],
    "stat_bar_stats": [{ "id": 4, "label": "Partner Restaurants", "value": "250", "icon_class": "bi bi-shop", "accent": "green", "sort_order": 1 }],
    "sections": {
      "about": { "key": "about", "eyebrow": "Who we are", "heading": "…", "heading_accent": "…", "image_url": "…", "extras": {}, "features": [] }
    },
    "meal_categories": [],
    "featured_restaurants": [],
    "testimonials": []
  }
}
```

**Lists versus keyed maps.** `nav_menus` and `sections` are objects keyed by `location` and by section `key`; everything else is an ordered list. The two keyed maps always encode as JSON objects — `{}` when nothing is published, never `[]`.

**No pagination.** Every group is the complete published set, in `sort_order` then `id` order. A home page needs all of it at once, and the tables are a few dozen rows.

## Business Rules & Edge Cases

- **Only published rows appear.** `is_published = false` removes a row everywhere — a nav link, a stat, a category, a card, a review, a section (which drops its whole `sections` key), or a single feature inside an otherwise published section.
- **Every list is always present, even when empty.** An absent key is `undefined` in JavaScript and throws on `.map()`, so `hero_stats`, `stat_bar_stats`, `meal_categories`, `featured_restaurants`, `testimonials` and all four `nav_menus` locations survive as `[]`. `HomePageService::menusByLocation()` re-adds the locations `groupBy()` dropped. `sections` is the exception: it is looked up by key rather than iterated, so an unpublished section is simply missing.
- **An unseeded database still answers.** `SiteSettingRepository::current()` falls back to an **unsaved** `SiteSetting` carrying the site's shipped branding, so a freshly migrated database returns the real wordmark rather than nulls. Unlike MealHub's `firstOrCreate`, reading the page does **not** write the row — a public GET must not have a side effect. Phase 10's admin update is what persists it.
- **Image resolution is the Resource's job, never the model's.** `ResolvesImageUrl` turns each `image`/`image_url` pair (and `logo`, `avatar`/`avatar_url`) into one **absolute** URL: an uploaded file always wins over an externally hosted one, and `null` means the record has neither. MealHub's model accessors returned root-relative paths, which a cross-origin SPA resolves against its own host.
  - Stored files live at `cms/{collection}/{variant}/{filename}` on the `public` disk, where the collection comes from each model's `IMAGE_COLLECTION` constant. Variants match MealHub's: `small` for the site logo and testimonial avatars, `medium` for meal categories and restaurant cards, `large` for section imagery. Phase 4's upload service must write to this same layout.
  - `image_url` exists because the seeded photography is hot-linked — a seeder cannot download it into storage.
- **A null image URL means "render nothing".** Clients must omit the image element rather than emit an empty `src`, which browsers resolve against the current document.
- **Nothing is pre-rendered for the DOM.** `icon_class`, `accent`, `variant` and `perk_variant` are bare semantic tokens the client maps to its own styling; `route_key` is an **opaque token the SPA maps to its own path**, not a Laravel route name (see the `NavMenu` model). `heading` and `heading_accent` arrive split so the client can colour the tail and join them back into one sentence.
- **Featured restaurants are one flat list.** MealHub's service returned them pre-chunked into carousel slides; how many cards make a slide is a DOM decision the client owns. The linked `user_id` is **not exposed** — it is null on every card today and publishing an internal account id on an anonymous endpoint buys the client nothing.
- **A how-it-works step's number is not stored.** It is the item's position in `features`, so hiding a step renumbers the rest rather than leaving a gap.
- **`extras` is always an object.** A section saved without extras ships `{}`, so a client can read a missing key without guarding the container. Which keys each section understands is `HomeSection::EXTRA_FIELDS`. One value needs care: the About badge's `badge_text` may contain a real newline, and that break is content — render it, don't collapse it.
- **Meal categories and featured restaurants are independent of their sections.** Neither table carries a foreign key to `home_sections`, so the cards ship even when the section envelope was never seeded, and vice versa.
- **The payload is a fixed 8 queries** regardless of row count — seven list reads plus the single eager load of section features. Pinned by a test.

## Data source

Seeded by `SiteSettingSeeder`, `HomeStatSeeder`, `NavMenuSeeder`, `HomeSectionSeeder`, `MealCategorySeeder`, `FeaturedRestaurantSeeder` and `TestimonialSeeder` with the content MealHub's public home page ships, so a client rendering from this payload produces the same site. Row counts and content rules are pinned by `tests/Feature/Database/SeederIntegrityTest.php`.

Each of the eight models has a factory with publish/unpublish and image states — see `FactoryIntegrityTest.php`.

## Tests

`tests/Feature/Cms/PublicHomeTest.php` — full payload structure, no-auth access, the unseeded-database fallback (and that it stays a read), every group present when empty, publish filtering and `sort_order` on each group, section keying with nested published features, derived step renumbering, `extras` as `{}` and its meaningful newline, absolute image URLs with uploaded-over-external precedence and null when neither, `user_id` never exposed, and a query-count assertion.

Ports MealHub's `HomePageCmsTest`. Its assertions were markup-level — the point there was proving the CMS cutover was a visual no-op — so what carries over is the rules behind them, asserted against JSON.
