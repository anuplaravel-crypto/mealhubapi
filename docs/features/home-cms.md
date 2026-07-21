# Home CMS

Everything the marketing home page renders — branding, navigation, the two stat rows, the six content sections, meal categories, featured restaurants, and reviews. Anonymous visitors read it all as **one payload**; admins edit it one resource at a time.

**Status:** ✅ Implemented (read). 🚧 Partial (write) — the admin surface currently covers **site settings and testimonials only**. The remaining six resources (nav menus, home stats, home sections, section features, meal categories, featured restaurants) are still read-only; their write surface is the rest of Phase 10 in [the roadmap](../roadmap.md).

## Endpoints

| Action | Endpoint | Auth |
| --- | --- | --- |
| Whole home payload | `GET /api/v1/home` | Public |
| Read branding | `GET /api/v1/admin/cms/site-settings` | `auth:sanctum` + `role:admin` |
| Save branding | `POST /api/v1/admin/cms/site-settings` | `auth:sanctum` + `role:admin` |
| List reviews | `GET /api/v1/admin/cms/testimonials` | `auth:sanctum` + `role:admin` |
| Add a review | `POST /api/v1/admin/cms/testimonials` | `auth:sanctum` + `role:admin` |
| Edit a review | `POST /api/v1/admin/cms/testimonials/{id}` | `auth:sanctum` + `role:admin` |
| Show/hide a review | `PATCH /api/v1/admin/cms/testimonials/{id}/toggle` | `auth:sanctum` + `role:admin` |
| Remove a review | `DELETE /api/v1/admin/cms/testimonials/{id}` | `auth:sanctum` + `role:admin` |

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

## Admin write surface

- Controllers: `App\Http\Controllers\Api\V1\Admin\Cms\{SiteSetting,Testimonial}Controller`
- Validated by: `App\Http\Requests\Cms\{UpdateSiteSettingRequest, SaveTestimonialRequest}`
- Shaped by: `App\Http\Resources\Admin\Cms\{AdminSiteSettingResource, AdminTestimonialResource}`
- Business logic: `App\Services\Cms\{SiteSettingService, TestimonialService}`, the latter on the shared `Cms\BaseCmsService`
- Data access: `App\Repositories\Cms\{SiteSettingRepository, TestimonialRepository}`, the latter on the shared `Cms\BaseCmsRepository`

**Admin reads are a different shape from public ones.** The public Resources deliberately hide the editorial fields, so each admin read goes through a thin variant that spreads the public one and appends what only an editor needs: `is_published` (an unpublished row is absent from the public payload entirely, so nothing there could report it), `has_uploaded_avatar` / `has_logo`, the raw `external_avatar_url` an editor puts back in a form field, and timestamps. The shared fields are never restated, so the two shapes cannot drift.

**No Policy, despite the ids.** A CMS record has no owner — `role:admin` is the entire authorization question, exactly as it is for newsletter subscribers. Ownership checks are required where there is an owner to check; here there is none. Ids arrive as plain integers rather than through route-model binding, because binding resolves the model from the *controller method's* type hint and a shared base could not name one; `findOrFail()` in the repository is the single not-found path instead.

**Edits are POST, not PUT.** Anywhere the payload can carry a file, PHP populates no uploaded-file bag on a PUT body — the same constraint the rider vehicle and restaurant document upserts answer the same way. Everything else keeps a real verb: PATCH for the one-field toggle, DELETE for a removal. MealHub's `POST .../{id}/delete` was a Blade-form workaround and does not survive the port.

**Two shapes, not one.** Testimonials are a reorderable collection and get the full five actions from `BaseCmsController`. Site settings are a singleton: read and save, with no list, create, delete or toggle to route. Inheriting five actions to expose two would advertise a shape the resource does not have, which is why `SiteSettingController` extends `Controller` directly.

## Business Rules & Edge Cases

- **Only published rows appear.** `is_published = false` removes a row everywhere — a nav link, a stat, a category, a card, a review, a section (which drops its whole `sections` key), or a single feature inside an otherwise published section.
- **Every list is always present, even when empty.** An absent key is `undefined` in JavaScript and throws on `.map()`, so `hero_stats`, `stat_bar_stats`, `meal_categories`, `featured_restaurants`, `testimonials` and all four `nav_menus` locations survive as `[]`. `HomePageService::menusByLocation()` re-adds the locations `groupBy()` dropped. `sections` is the exception: it is looked up by key rather than iterated, so an unpublished section is simply missing.
- **An unseeded database still answers.** `SiteSettingRepository::current()` falls back to an **unsaved** `SiteSetting` carrying the site's shipped branding, so a freshly migrated database returns the real wordmark rather than nulls. Unlike MealHub's `firstOrCreate`, reading the page does **not** write the row — a public GET must not have a side effect, and neither does the admin's own read. `SiteSettingRepository::persist()` is the only path that ever inserts, which is what keeps the table a singleton; it force-fills the key, because `id` is not fillable and a first save on a table whose auto-increment had already advanced would otherwise create a row the read could never find again. `is_persisted` on the admin Resource is how a client tells the shipped defaults from branding an admin actually chose.
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

### Write path

- **An upload always beats a link, and clears it.** Saving a file writes `avatar` / `logo` and nulls the companion `avatar_url`. The two columns are a preference, not alternatives — the Resource resolves the upload first — so a surviving link would be a value that can never be shown and would silently reappear the day the upload was removed. `SiteSetting` has no `logo_url` companion at all: the site's own mark is never hot-linked.
- **Saving without a file keeps the stored image.** Correcting a typo in the meta description must not cost an admin their branding. There is deliberately **no "remove the upload" action** yet — an image can be replaced but not cleared back to nothing, which is a known gap inherited from MealHub rather than a decision.
- **Replacement is an argument, never a follow-up delete.** Every write passes `replacing:` to `ImageUploadService::store()`, so the outgoing file's four variants are reclaimed only after the new ones are safely written. A failure mid-encode cannot leave a row pointing at a file that has already been deleted.
- **Deleting a row deletes its images first.** The stored filename is the only reference to four variants, so dropping the row first would orphan all four beyond reach.
- **A created row is re-read before it is returned.** Eloquent's `create()` hands back the instance built from the payload, which knows nothing about columns the *database* defaulted — a review created without an explicit `is_published` came back with it as `null`, leaving the client unable to tell whether what it just created was live.
- **Publishing state is a toggle, not a set.** The client sends no target state, so a replayed request cannot re-publish something an admin had just taken down. The updated row comes back so the caller reads the new state instead of assuming its own guess landed. Omitting `is_published` from a *save* leaves the flag as it was, rather than hiding the row.
- **A new row is appended, not inserted at 0.** `sort_order` defaults to one past the current maximum, scoped to the row's group where the table has one. An explicit `sort_order` is honoured.
- **Admin lists are not paginated.** These are editorial sets of a few dozen rows whose order is itself editable, and an admin cannot sensibly reorder a list they can only see one page of. This is the documented exception to the project's "lists go through `paginatedResponse()`" rule; the newsletter list, whose rows are unbounded and unordered, still paginates.
- **Validation lengths mirror the column widths.** `tests/Feature/Database/` runs on SQLite, which ignores varchar limits, so the Form Request is what actually stops an over-long value reaching MySQL. Image format and size rules are never restated — they come from `Requests\Concerns\ValidatesUploadedImage`, which excludes SVG because it is an XSS vector on a public disk.

## Data source

Seeded by `SiteSettingSeeder`, `HomeStatSeeder`, `NavMenuSeeder`, `HomeSectionSeeder`, `MealCategorySeeder`, `FeaturedRestaurantSeeder` and `TestimonialSeeder` with the content MealHub's public home page ships, so a client rendering from this payload produces the same site. Row counts and content rules are pinned by `tests/Feature/Database/SeederIntegrityTest.php`.

Each of the eight models has a factory with publish/unpublish and image states — see `FactoryIntegrityTest.php`.

## Tests

`tests/Feature/Cms/PublicHomeTest.php` — full payload structure, no-auth access, the unseeded-database fallback (and that it stays a read), every group present when empty, publish filtering and `sort_order` on each group, section keying with nested published features, derived step renumbering, `extras` as `{}` and its meaningful newline, absolute image URLs with uploaded-over-external precedence and null when neither, `user_id` never exposed, and a query-count assertion.

Ports MealHub's `HomePageCmsTest`. Its assertions were markup-level — the point there was proving the CMS cutover was a visual no-op — so what carries over is the rules behind them, asserted against JSON.

`tests/Feature/Cms/AdminSiteSettingTest.php` — the saved read and the defaulted one, that reading never creates the row, that the first save does (at the singleton id) and a second updates it, logo upload across every variant on the public disk with the private one left empty, replacement reclaiming the old variants, a save without a file keeping the existing logo, required fields, an over-long name, an SVG refusal, 401 anonymous, and 403 for each of the other three roles.

`tests/Feature/Cms/AdminTestimonialTest.php` — display order, unpublished rows present in the admin list but absent from the public one, a query-count assertion, create/edit/toggle/delete, `sort_order` appended and overridden, an upload clearing the external link, an external link surviving without one, replacement and deletion reclaiming files, 404 on each id-taking action, validation failures, 401 anonymous, and 403 for each other role on the list, on create, and on every write endpoint.
