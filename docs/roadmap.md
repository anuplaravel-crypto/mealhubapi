# Migration Roadmap — MealHub → MealHubApi

Hand-written planning document. **Not** auto-generated — `php artisan docs:generate` does not
touch this file.

How to port the Blade app [MealHub](../../MealHub) into this API, domain by domain, ordered so
that every increment leaves the API in a working, tested state.

Baseline inventory taken 2026-07-21 — MealHub: 143 web routes, 33 controllers, 43 services,
17 repositories, 16 models. MealHubApi: 6 auth controllers, 1 service, 16 models, 31 routes.

---

## What is already done

The port is further along than a `composer.json` diff suggests. **The entire data layer is
finished**; the work that remains is the HTTP layer.

| Layer | MealHub | MealHubApi | Status |
| --- | --- | --- | --- |
| Migrations | 21 | 20 | ✅ Complete — `users` verified column-for-column identical; API adds `personal_access_tokens` and `rememberToken`, drops nothing |
| Models | 16 | 16 | ✅ Complete — including the three SPA adaptations recorded in CLAUDE.md |
| Seeders | 9 | 10 | ✅ Complete (plus `DevUserSeeder`) |
| Factories | 1 | 11 | ✅ Ahead of MealHub |
| Auth endpoints | 4 roles | 4 roles | ✅ Phase 1 complete — mobile OTP deferred to Phase 1b |
| Everything else | 33 controllers | 6 | ❌ This roadmap |

Consequently almost every phase below has **Migrations / Models / Factories / Seeders = done**.
Exceptions are called out explicitly.

---

## Architecture decisions

### Three-tier: Controller → Service → Repository

MealHubApi originally shipped a two-tier architecture (thin controller → fat service, no
repository layer). **That decision has been reversed**: the repository layer is kept, to stay
consistent with the reference project and make porting mechanical rather than interpretive.

MealHub's CLAUDE.md mandates `Controller → Service → Repository`; MealHubApi's did not mention
repositories at all. Phase 0 amended MealHubApi's CLAUDE.md so the two agree — without that edit
a future session will "helpfully" collapse the layer back into services.

| Tier | Does | Must not do |
| --- | --- | --- |
| Controller | validate (Form Request) → delegate → shape response | business logic, Eloquent |
| Service | business rules, transactions, orchestrating several repositories | direct `Model::query()` |
| Repository | Eloquent queries only | business rules, Request/Response objects, mail |

Repositories return models and collections — never arrays, never DTOs.

### Role authorization ✅ closed in Phase 1

`auth:sanctum` proves a token is valid, not whose role it carries — so a customer's token used
to be accepted at `/api/v1/admin/logout`. `app/Http/Middleware/EnsureUserHasRole.php` (aliased
`role`) now gates every authenticated route, and `tests/Feature/Auth/RoleGateTest.php` pins it.

**Every authenticated route from here on must carry `role:` as well as `auth:sanctum`.** Without
it, Phase 10 or 11 would mean "any logged-in customer can edit the home page".

---

## Phase order

```
0. Foundations ✅
   │
1. Auth hardening ✅ ┬─→ 2. Geo reference (public) ✅       1b. Mobile OTP (deferred)
                    ├─→ 3. Public Home CMS (public, read-only) ✅
                    └─→ 4. Media foundation ✅┬─→ 5. Profile ✅
                                              ├─→ 8. Rider vehicle ✅
                                              ├─→ 9. Restaurant documents
                                              └─→ 10. Admin CMS write
                         6. Notifications ✅ ──┴─→ 11. Admin user mgmt ─→ 12. Dashboards
                         7. Newsletter ✅
                        13. Terms & conditions (net-new, backlog)
```

---

## Phase 0 — Foundations ✅ Complete

No endpoints, so the API stays green by definition. Passing condition: the existing auth tests
still pass — they do (`php artisan test --compact`: 75 passed).

Delivered: `app/Repositories/{BaseRepository,UserRepository}.php` (with `AuthService` rewired
through `UserRepository`), `paginatedResponse()` / `noContentResponse()` / required-status
`errorResponse()` on the `ApiResponse` trait, `App\Exceptions\DomainException` plus four render
callbacks in `bootstrap/app.php`, `tests/Feature/Api/{ResponseEnvelopeTest,ExceptionEnvelopeTest}.php`,
and [features/api-conventions.md](features/api-conventions.md).

**One deviation from the plan below:** `PasswordResetRepository` was **not** built. MealHub's
version reads and writes the `password_reset_tokens` table, which **MealHubApi does not have** —
password reset here is OTP-based on the `users` table (`otp` / `otp_expires_at`), so
`UserRepository` already covers it. Building the class would have meant queries against a
non-existent table. Do not add it in Phase 1; drop the reference and use `UserRepository`.

### 0.1 Repository layer

New base folder `app/Repositories/`, mirroring MealHub's structure:

```
app/Repositories/
├── BaseRepository.php
├── Cms/{FeaturedRestaurant,HomeSection,HomeStat,MealCategory,
│        NavMenu,SectionFeature,SiteSetting,Testimonial}Repository.php
├── CustomerRepository.php              ├── LocationRepository.php
├── NewsletterSubscriberRepository.php  ├── NotificationRepository.php
├── PasswordResetRepository.php         ├── RestaurantRepository.php
├── RiderRepository.php                 ├── RiderVehicleRepository.php
└── UserRepository.php
```

One deliberate improvement over MealHub: MealHub has no `BaseRepository`, so
`findOrFail/create/update/delete` is copy-pasted across all 17 classes. Introduce an abstract
`BaseRepository` here — concrete classes then declare only their model and their genuinely
specific queries (`published()`, `nextSortOrder()`).

**Built in Phase 0:** `BaseRepository`, `UserRepository` (Phase 1 depends on them).
`PasswordResetRepository` was dropped — see the deviation note above. The rest arrive with their
domain — see the table below.

| Repository | Phase |
| --- | --- |
| `UserRepository` | 0 → 1 |
| `LocationRepository` | 2 |
| `Cms/*` (7 read classes) | 3 (read) → 10 (write) |
| `Cms/SectionFeatureRepository` | 10 — reads go through `HomeSectionRepository`'s eager load |
| `NotificationRepository` | 6 ✅ |
| `NewsletterSubscriberRepository` | 7 ✅ |
| `RiderVehicleRepository` | 8 ✅ |
| `CustomerRepository`, `RestaurantRepository`, `RiderRepository` | 11 |

### 0.2 Standard API response format

Already implemented: `App\Http\Traits\ApiResponse` — `successResponse()` / `errorResponse()`
producing `{success, data, message}` and `{success, message, errors}`.

Three gaps, all closed in Phase 0 and documented in
[features/api-conventions.md](features/api-conventions.md):

1. **No pagination support.** Passing a paginator to `successResponse()` nests Laravel's own
   `{data, links, meta}` inside `data`, so the client sees `data.data.data`. Add
   `paginatedResponse()` that lifts `meta` to the envelope's top level.
2. **No 204 path** — the delete endpoints in Phases 7 and 10 need one.
3. **`errorResponse()` defaults to 422**, but most manual errors are 400/403/404. A forgotten
   status argument silently returns the wrong code. Make the status explicit.

Document the envelope and pagination shape in `docs/features/api-conventions.md` — that file is
the contract MealHubReact codes against.

### 0.3 Global exception handling

Already implemented: `bootstrap/app.php` `withExceptions` → `shouldRenderJsonWhen` + `respond`,
reshaping every `api/*` error into the project envelope.

Gaps found, all closed in Phase 0:

1. 🔴 **`ModelNotFoundException` leaks the internal class name.** A failed route-model binding
   returns `No query results for model [App\Models\User] 5`. Phase 2 introduces the first bound
   parameter, so this surfaces immediately. Map it to `Resource not found.` + 404.
2. **429 is unshaped.** Auth routes already carry `throttle:6,1` but `ThrottleRequestsException`
   has no envelope handling; lift `Retry-After` into the body so the client can show a countdown.
3. **`AuthorizationException` → 403** messaging, settled before Phase 6 adds the first Policy.
4. **No base for domain errors.** Add `App\Exceptions\DomainException` carrying a status and
   message, mapped in the handler, so services stop reaching for `abort()` or raw
   `ValidationException`.
5. **Pin the production 500 shape** with a test asserting no stack trace escapes when
   `APP_DEBUG=false`.

Two implementation notes for whoever touches the handler next:

- **Type-hint against the *prepared* exception.** `Handler::render()` runs
  `prepareException()` *before* the `render()` callbacks, so `ModelNotFoundException` has already
  become `NotFoundHttpException` and `AuthorizationException` has become
  `AccessDeniedHttpException` by the time a callback sees it. Type-hinting the original class
  means the callback silently never fires. The 404 callback therefore covers every `api/*`
  `NotFoundHttpException`, unknown routes included — all of them answer `Resource not found.`
- **`respond()` passes through anything already enveloped** (any body with a `success` key), so
  the `render()` callbacks keep their extra keys (`retry_after`) and headers (`Retry-After`).
  Without that guard `respond()` would rebuild the body as `{success, message, errors}` and drop
  them.

### 0.4 CLAUDE.md amendments

- Replace the two-tier architecture description with the three-tier contract above.
- Add the Definition of Done below as its own section, so it is enforced every session.

### 0.5 Phase 0 tests

- `tests/Feature/Api/ResponseEnvelopeTest.php` — success, error, paginated, 204 shapes
- `tests/Feature/Api/ExceptionEnvelopeTest.php` — one assertion per status: 401, 403, 404, 422,
  429, 500

---

## Phase 1 — Auth hardening & role gate ✅ Complete

Everything later sits behind this, and it closes the authorization hole.
`php artisan test --compact`: 100 passed.

**Deviation: mobile-OTP login was deferred to Phase 1b (below), not built.** The rest of the
phase shipped as planned.

| Category | Work |
| --- | --- |
| Controllers | ✅ `Api/V1/Auth/BaseAuthController` extended with `resendOtp()`, `changePassword()`. `MobileOtpController` → Phase 1b. |
| Services | ✅ `AuthService::resendOtp()` / `changePassword()`. No per-role services added — the four `Auth/*AuthService` classes stay collapsed into one role-parameterized service. |
| Repositories | ✅ `UserRepository` (Phase 0), plus `revokeOtherTokens()` |
| Models | ✅ `User` |
| FormRequests | ✅ `Auth/ChangePasswordRequest`, `Auth/ResendOtpRequest`. `Send`/`VerifyMobileOtpRequest` → Phase 1b. |
| Resources | ✅ `UserResource` |
| Policies | — |
| Routes | ✅ `resend-otp` + `change-password` per role; `role:{role}` applied to `change-password` and `logout` |
| Notifications | ✅ `OtpNotification` role-parameterized. The three `*VerifyAccountNotification` classes were **not** ported as a link-based email — see the note below. |
| Events | — |
| Seeders / Factories | ✅ |
| Feature Tests | ✅ `ChangePasswordTest`, `ResendOtpTest`, `RoleGateTest`. `MobileOtpTest` → Phase 1b. |
| Documentation | ✅ `docs/features/authentication.md` |

**Key artifact:** `app/Http/Middleware/EnsureUserHasRole.php`, aliased in `bootstrap/app.php` as
`role:admin` etc. The alternative — Sanctum token abilities issued at login — is rejected: a
token minted before a role change would keep the old ability, and route files read worse.

**On the `*VerifyAccountNotification` port.** Those three classes email an activation *link* to
MealHub's own Blade routes (`route('verify.new.restaurant', …)`). Reproducing that here would
reintroduce link-based verification into an OTP-based API, and point it at a URL that does not
exist — the SPA's address is not known until `FRONTEND_URL` lands in Phase 7. What was actually
worth porting is the only thing that differed between the three: a single "what you can do once
verified" line. That is now the `$role` argument to `OtpNotification`. Do not add a fourth
notification class for this.

**Two testing gotchas found here**, both worth knowing before writing Phase 2's tests:

- The sanctum guard **memoizes the resolved user for the lifetime of a test method**. A test that
  makes two authenticated requests as *different* users sees the first user both times. Use a
  data provider (one case per role) rather than looping inside one test.
- `Sanctum::actingAs()` yields a `TransientToken`, which has no primary key — so it breaks any
  code that keys off `currentAccessToken()`, including `UserRepository::revokeOtherTokens()`.
  These tests mint real tokens and send a `Bearer` header, matching the rest of `tests/Feature/Auth/`.

---

## Phase 1b — Mobile-OTP login (deferred, backlog)

Ports `Frontend\AuthController@sendMobileOtp` / `verifyMobileOtp` — a passwordless customer login
by mobile number. Deferred out of Phase 1 rather than dropped; nothing depends on it.

**Two blockers, both must be resolved before this is worth building:**

1. **No SMS delivery exists.** MealHub's `sendMobileOtp` generates an OTP, stores it, and returns
   it to a controller that discards it — while telling the user "a one-time code has been sent to
   your mobile." Its `config/services.php` has an `sms` block with a `log` driver, but **no code
   reads it** (`grep -rn "services.sms" app/` finds nothing). Porting as-is ships a login flow no
   legitimate user can complete. Needs an SMS provider decision, which is a dependency addition
   and therefore needs approval per CLAUDE.md.
2. **`users.mobile` is nullable and not unique.** A passwordless login keyed on it resolves to an
   arbitrary matching row. Needs a migration adding a unique index — realistically unique per
   `(role, mobile)`, since the four roles share the table.

When it does land: `Api/V1/Auth/MobileOtpController`, `Auth/SendMobileOtpRequest`,
`Auth/VerifyMobileOtpRequest`, `UserRepository::findByMobileAndRole()`, and
`tests/Feature/Auth/MobileOtpTest.php`. Note MealHub's version is **customer-only** and gives the
mobile OTP a 5-minute TTL rather than the 10 minutes used for email.

---

## Phase 2 — Geo reference data (public, read-only) ✅ Complete

Zero dependencies, and MealHubReact's registration form is blocked without it.
`php artisan test --compact`: 112 passed.

| Category | Work |
| --- | --- |
| Controllers | ✅ `Api/V1/LocationController` — `countries()`, `counties()`, `cities()` (ports `Frontend\PagesController`; the `getCiiesByCounty` typo was not carried over) |
| Services | ✅ `LocationService` — flat under `app/Services/`, matching `AuthService`, not MealHub's `Services/Location/` sub-namespace |
| Repositories | ✅ `LocationRepository`, rebased on `BaseRepository<Country>` |
| Models | ✅ `Country`, `County`, `City` — `HasFactory` added to all three |
| FormRequests | — (bound path parameters only) |
| Resources | ✅ `CountryResource`, `CountyResource`, `CityResource` |
| Policies / Notifications / Events | — |
| Routes | ✅ `GET v1/countries`, `v1/countries/{country}/counties`, `v1/counties/{county}/cities` — public, nested rather than MealHub's flat `cities/{countyId}` |
| Seeders / Factories | ✅ `LocationSeeder` (3 countries / 8 counties / 24 cities), plus net-new `Country`/`County`/`City` factories |
| Feature Tests | ✅ `tests/Feature/Location/LocationTest.php` (11 tests); factory states added to `FactoryIntegrityTest` |
| Documentation | ✅ [features/locations.md](features/locations.md) |

**Three decisions worth keeping:**

- **`LocationRepository` extends `BaseRepository<Country>`** — the root of the hierarchy — and the
  two child lookups query **through the parent's relation** (`$country->counties()`), not
  `County::query()->where(...)`. The scoping then cannot be forgotten at a call site, and no second
  repository class is needed for what is one cascade.
- **A parent with no children is `200` + `[]`, not `404`.** Only an unknown parent id is a 404, and
  it comes from route-model binding rather than a rule — which is why this phase adds no Form
  Request. This is the first bound path parameter in the codebase, so it is also the first live
  exercise of Phase 0.3's `NotFoundHttpException` remap.
- **No pagination.** The whole tree is 35 rows and a dropdown needs every option at once; paging a
  cascade would make the client fetch pages to find an option.

**Factories were net-new** despite the table above saying "Seeders / Factories ✅" — `LocationSeeder`
alone cannot express "a country with no counties", which the empty-child-list test needs. Country
and county names use `fake()->unique()`, since a cascade with two identically named siblings is
indistinguishable to a client.

---

## Phase 3 — Public Home CMS (read-only) ✅ Complete

Unblocks the entire React public site with no auth and no uploads — the highest value per unit
of work in the roadmap. `php artisan test --compact`: 140 passed.

| Category | Work |
| --- | --- |
| Controllers | ✅ `Api/V1/HomeController@index` — one endpoint returning the whole home payload |
| Services | ✅ `Cms/HomePageService`, absorbing MealHub's view composer as well as its service — see the note below |
| Repositories | ✅ `Cms/*Repository` × **7**, all rebased on `BaseRepository`, read methods only. `SectionFeatureRepository` was **not** built — see the deviation below. |
| Models | ✅ all 8 |
| FormRequests | — (the endpoint takes no input at all) |
| Resources | ✅ `SiteSettingResource`, `NavMenuResource`, `HomeStatResource`, `HomeSectionResource` (nesting `SectionFeatureResource`), `MealCategoryResource`, `FeaturedRestaurantResource`, `TestimonialResource`, plus the shared `Resources/Concerns/ResolvesImageUrl` trait Phases 5, 8, 9 and 10 reuse |
| Policies / Notifications / Events | — |
| Routes | ✅ `GET v1/home` — public. No per-resource reads added; nothing has asked for one yet. |
| Seeders / Factories | ✅ |
| Feature Tests | ✅ `tests/Feature/Cms/PublicHomeTest.php` (28 tests), including the query-count assertion |
| Documentation | ✅ [features/home-cms.md](features/home-cms.md) |

**Deviation: `SectionFeatureRepository` was not built.** Reads reach features through
`HomeSectionRepository::publishedKeyed()`'s eager load, and MealHub's class holds only write paths
plus `nextSortOrder()` — so building it now would have meant an empty class. It arrives in Phase 10
with the write surface that needs it. Same reasoning as Phase 0's dropped `PasswordResetRepository`.

**Five decisions worth keeping:**

- **`SiteSettingRepository::current()` does not `firstOrCreate`.** MealHub's did, which is how an
  unseeded database still rendered its branding — but the only caller here is an anonymous public
  GET, and a read endpoint that writes on first hit is a surprise in an API. It returns an unsaved
  instance carrying the defaults instead; Phase 10's admin update persists the row.
- **Branding and navigation are in the payload, not a separate call.** MealHub kept them out of
  `HomePageService` because a view composer already pushed them into the shared layout, which the
  auth pages needed too. A cross-origin SPA has no layout to compose into, so that split would just
  mean two round trips for one screen.
- **Every list is guaranteed present, even when empty.** An absent key is `undefined` in JavaScript
  and throws on `.map()`, so `menusByLocation()` re-adds the locations `groupBy()` dropped, and the
  two stat placements fall back to empty collections. `sections` is deliberately the exception — it
  is looked up by key, never iterated.
- **Featured restaurants ship as one flat list**, not MealHub's pre-chunked carousel slides: how
  many cards make a slide is a DOM decision the client owns. `user_id` is not exposed either.
- **The image layout is now pinned by `ResolvesImageUrl`:** `cms/{collection}/{variant}/{filename}`
  on the `public` disk, absolute URLs, uploaded file beating external link. Phase 4's
  `ImageUploadService` **must write to this same layout** — nothing but a docblock enforces the
  agreement across the two classes.

---

## Phase 4 — Media / upload foundation ✅ Complete

An enabler with no endpoints of its own. Phases 5, 8, 9 and 10 all need it; building it four
times is the main duplication risk in this migration. `php artisan test --compact`: 165 passed.

**Deviation: `MediaController@show` was not built.** Nothing writes a private file until Phase 5,
so the controller would have had a placeholder authorization path and no real data to stream. The
service side of it — `pathFor()`, existence-checked against the private disk — ships here and is
tested; the route and its Policy land with the first private upload. Same reasoning as Phase 0's
dropped `PasswordResetRepository` and Phase 3's `SectionFeatureRepository`.

| Category | Work |
| --- | --- |
| Controllers | ⏸ `Api/V1/MediaController@show` deferred to Phase 5 — see above. Public images are served from the public disk as absolute URLs, never proxied through PHP the way MealHub's `profile/image` route does. |
| Services | ✅ `Media/ImageUploadService` — one service replacing MealHub's split `Cms/CmsImageService` + `Profile/ProfileImageService`, plus the net-new `Media/MediaPlacement` enum |
| Repositories | — (this tier touches the filesystem, not the database) |
| Models | ✅ the five `IMAGE_COLLECTION` constants are now honored by a writer |
| FormRequests | ✅ `Concerns/ValidatesUploadedImage` — merges MealHub's near-duplicate `Cms/Concerns/ValidatesCmsImage` and `Profile/Concerns/ValidatesProfilePicture` |
| Resources | ✅ `Concerns/ResolvesImageUrl` rebased onto `MediaPlacement` |
| Policies / Routes / Notifications / Events | — |
| Feature Tests | ✅ `tests/Feature/Media/ImageUploadServiceTest.php` (25 tests) with `Storage::fake()` |
| Documentation | ✅ [features/media-uploads.md](features/media-uploads.md) |

**Dependency:** `intervention/image ^3.11` installed (3.11.8), matching MealHub's pin, with
approval. It renders through `ImageManager::gd()` — Imagick is not installed on this PHP.

**Three decisions worth keeping:**

- **`MediaPlacement` is the single source of truth for the storage layout.** Disk, variant sizes
  and path shape live on the enum, and *both* the writer (`ImageUploadService`) and the reader
  (`ResolvesImageUrl`) build paths through `MediaPlacement::path()`. Phase 3 left the layout
  restated in a trait comment that merely asked this phase to match it — that is exactly how a
  reader and a writer quietly stop agreeing, so the constants were deleted rather than duplicated.
  `ImageUploadServiceTest` closes the loop with a round-trip: store a file, resolve it as a
  Resource would, assert the resolved path exists.
- **Placement, not two services.** MealHub's two image services differed only in disk, path prefix
  and how big "large" is — three values, which is an enum, not a second class. Where they had
  drifted apart the better behavior won for both: personal images now preserve PNG/WebP
  transparency instead of being forced to JPEG.
- **Replacement is a `store()` argument, not a caller's responsibility.** `store(..., replacing:)`
  deletes the outgoing file's variants *after* the new ones are written. Four future phases each
  replace images; leaving cleanup to each call site means one of them forgets and orphans files
  nothing can reach.

**The enum lives in `app/Services/Media/`, not `app/Enums/`** — CLAUDE.md requires approval for new
base folders and there is no `app/Enums` yet. Move it if that folder is ever introduced.

**`php artisan storage:link` is now required for a working dev environment** — the symlink did not
exist in this clone, and without it CMS images are written but every URL 404s.

---

## Phase 5 — Profile (all four roles) ✅ Complete

The first phase to store a private file, and therefore the one that finally built Phase 4's
deferred streaming controller. `php artisan test --compact`: 200 passed.

| Category | Work |
| --- | --- |
| Controllers | ✅ `Api/V1/ProfileController` — `show`, `update`, `updatePicture`, role read from the token, replacing MealHub's four near-identical `ProfileController`s. Plus ✅ `Api/V1/MediaController@show` (Phase 4's deferral, closed). |
| Services | ✅ `ProfileService` — flat under `app/Services/`, matching `AuthService`. MealHub's four role services turned out to be byte-identical apart from the image directory, so there are no private per-role methods either. |
| Repositories | ✅ `UserRepository::withLocation()` |
| Models | ✅ `User` — plus `IMAGE_COLLECTION = 'profile'`, the leaf the owning role prefixes |
| FormRequests | ✅ `Profile/UpdateProfileRequest`, `Profile/UpdateProfilePictureRequest` (on Phase 4's `ValidatesUploadedImage`). `UpdateProfileWithPictureRequest` deliberately not ported. |
| Resources | ✅ `UserResource` extended — `image_url`, nested `country`/`county`/`city`, and `address2` (previously missing) |
| Policies | — (self-scoped: always `$request->user()`, never an ID from the request) |
| Routes | ✅ `GET v1/profile`, `PUT v1/profile`, `POST v1/profile/picture`, `GET v1/media/profile-picture` — `auth:sanctum`, not role-gated. The skeleton's `GET api/user` closure was **removed**: it returned a raw model, bypassing both the Resource layer and the envelope. |
| Notifications / Events | — |
| Feature Tests | ✅ `tests/Feature/Profile/ProfileTest.php` (17) and `ProfilePictureTest.php` (18) |
| Documentation | ✅ [features/profile.md](features/profile.md); [features/media-uploads.md](features/media-uploads.md) updated off "no endpoints yet" |

**Four decisions worth keeping:**

- **`image_url` is an endpoint address, not a storage URL** — and it is null unless the resource
  *is* the caller, because the streaming route serves nobody else's file. This is the one place
  `UserResource` cannot use Phase 3's `ResolvesImageUrl` trait: that trait is hard-wired to
  `MediaPlacement::Cms` because a public URL is the only thing it can build.
- **The media route takes no id, so it needs no Policy.** The roadmap's Phase 5 line ("self-scoped,
  never an ID from the request") and its Definition of Done ("a Policy wherever an ID arrives from
  the URL") only agree if the streaming route stays self-scoped — so it does. Phase 6's
  `v1/notifications/{notification}` turned out to be the first route needing one, and created
  `app/Policies/`; Phase 9's `v1/admin/restaurants/{user}/documents/{slot}` is the next.
- **Location ids are checked for existence, not for consistency**, matching `RegisterRequest`: a
  `city_id` from a different county is accepted. Tightening that is a change to both requests, not
  to this endpoint alone, so it was left as known debt rather than a silent divergence.
- **`whenLoaded(..., null)` rather than a missing key.** `country`/`county`/`city` are always
  present, null where they were not eager-loaded — an absent key and a null are the same in PHP but
  not in a typed client. Resolving them inside the Resource instead would be three queries per user
  on every list that later reuses it.

**A third instance of the sanctum memoization gotcha:** the "user A cannot read user B's picture"
test cannot upload as A and then fetch as B — the guard would answer both as A, and the test passes
for the wrong reason. It seeds A's file straight onto the fake disk and makes exactly one
authenticated request.

---

## Phase 6 — Notifications ✅ Complete

The first phase where an id arrives from the URL, and therefore the one that finally created
`app/Policies/`. `php artisan test --compact`: 242 passed.

| Category | Work |
| --- | --- |
| Controllers | ✅ `Api/V1/NotificationController` — `index`, `unread`, `markAsRead`, `markAllAsRead`, `toggleRead`, `destroy`. One controller replacing MealHub's four identical per-role ones (24 routes → 6). |
| Services | ✅ `NotificationService` — flat under `app/Services/`, matching `AuthService` and `ProfileService`. MealHub's abstract-plus-subclass split did not survive; see below. |
| Repositories | ✅ `NotificationRepository`, rebased on `BaseRepository<DatabaseNotification>` — the one repository whose model is the framework's rather than ours |
| Models | ✅ `notifications` migration already ported; uses Laravel's `DatabaseNotification`, no custom model |
| FormRequests | — (no endpoint takes a body; the page size is fixed rather than client-supplied) |
| Resources | ✅ `NotificationResource`, served through the Phase 0 `paginatedResponse()` — the first live use of that envelope |
| Policies | ✅ `NotificationPolicy` — owner-only, **the first Policy in the codebase**, registered by hand in `AppServiceProvider` |
| Routes | ✅ `v1/notifications/*` under `auth:sanctum`, no `role:` gate — same reasoning as the profile routes |
| Notifications | ✅ `RegistrationNotification` and `AccountStatusNotification`, each one role-parameterized class replacing three. `Concerns/FormatsUserDetails` kept. |
| Events | ✅ `UserStatusChanged` + `Listeners/SendAccountStatusNotification`, net-new. No producer until Phase 11 — that is the point. |
| Seeders / Factories | ✅ net-new `Database\Factories\DatabaseNotificationFactory` |
| Feature Tests | ✅ `tests/Feature/Notifications/NotificationTest.php` (29) and `AccountNotificationTest.php` (12); the factory added to `FactoryIntegrityTest` |
| Documentation | ✅ [features/notifications.md](features/notifications.md). `mkdocs.yml` `nav:` also gained the two Phase 4/5 pages that were never listed. |

**Five decisions worth keeping:**

- **Ownership is a 403, not a silent no-op.** MealHub scoped every lookup to the notifiable, so an
  id belonging to somebody else matched nothing and still answered "success" — a delete that
  deleted nothing reported success. Here the row is resolved by route-model binding and the Policy
  decides: another user's id is 403, an unknown id is 404. The Policy compares `notifiable_type` as
  well as `notifiable_id`; there is only one notifiable today, which is exactly why that check is
  easy to omit now and expensive to add back later.
- **`Gate::policy()` is registered by hand and must stay that way.** Policy discovery maps
  `App\Models\X` to `App\Policies\XPolicy`, and `DatabaseNotification` is in neither namespace.
  The failure mode is uniform 403s rather than an open door — `Gate::authorize()` denies an ability
  it cannot resolve — but only a test exercising the endpoints would notice.
- **One service, no subclasses.** MealHub's abstract `NotificationService` existed so each role's
  subclass could override `extraPayload()` and lift different keys out of the stored blob for its
  own popup script. An API has no popup: the Resource ships `data` whole. That removed the only
  reason the subclasses existed.
- **`type` is the payload's semantic token, never the notification class name.** The column holds
  `App\Notifications\RegistrationNotification`; exposing it would leak an internal name and break
  clients on a rename. Same family of decision as `route_key` and the dropped CSS helpers.
- **`AccountStatusNotification` ships with an event seam and no producer.** Phase 11's toggle fires
  `UserStatusChanged`; the notification is a listener's job. MealHub's three management services
  each called `$user->notify(...)` inline, and Phase 11 has a *second* consequence for the same
  moment (a deactivated rider's `RiderVehicle.is_active`). That is a second listener, not a second
  line in the toggle. Unlike the deferrals in Phases 0/3/4 this is not a placeholder — the class and
  the listener are complete and tested by dispatching the event.

**One thing this phase changed outside its own domain:** `AuthService::register()` now notifies
every admin (`UserRepository::admins()`) when a customer, restaurant or rider signs up — at signup,
not at OTP verification, since an admin's approval queue should not depend on whether the applicant
has opened their email. An admin registering another admin raises nothing.

**No dashboard link in the activation email.** MealHub's ended with a button pointing at its own
Blade route; there is no such URL until `FRONTEND_URL` lands in Phase 7 — the same reason
`OtpNotification` sends a code rather than a link. Add the action there, not a placeholder here.

---

## Phase 7 — Newsletter ✅ Complete

The first phase to email a **link** rather than a code, and therefore the one that introduces
`FRONTEND_URL`. `php artisan test --compact`: 274 passed.

| Category | Work |
| --- | --- |
| Controllers | ✅ `Api/V1/NewsletterController` — `subscribe`, `confirm(token)`, `unsubscribe(token)`; ✅ `Api/V1/Admin/NewsletterController` — `index`, `destroy`, **the first controller under `Api/V1/Admin/`** |
| Services | ✅ `NewsletterService` — flat under `app/Services/`, matching `AuthService`/`LocationService`/`ProfileService`/`NotificationService`, not the `Services/Newsletter/` sub-namespace above. One class is not a sub-namespace. |
| Repositories | ✅ `NewsletterSubscriberRepository`, rebased on `BaseRepository<NewsletterSubscriber>` — plus `paginateLatest()`, since the base `paginate()` applies no ordering |
| Models | ✅ `NewsletterSubscriber` — double opt-in, `status` / `is_mailable` derived from the two timestamps and never stored. Preserved. |
| FormRequests | ✅ `Newsletter/SubscribeRequest`. MealHub's `failedValidation()` override was **not** ported — the handler in `bootstrap/app.php` already shapes 422. |
| Resources | ✅ `NewsletterSubscriberResource` — one class for both the public and admin surfaces; `token` is never exposed by either |
| Policies | — (`role:admin` suffices; a subscriber has no owner. See the note below.) |
| Routes | ✅ `POST v1/newsletter/subscribe` (throttle 5/1), `POST v1/newsletter/confirm/{token}`, `POST v1/newsletter/unsubscribe/{token}` (throttle 10/1), `v1/admin/newsletter*` |
| Notifications | ✅ `NewsletterConfirmationNotification` — flat under `app/Notifications/`, not MealHub's `Notifications/Newsletter/`. Sent to the subscriber row itself, which is `Notifiable` for exactly this reason. |
| Events | — |
| Feature Tests | ✅ `tests/Feature/Newsletter/NewsletterSubscriptionTest.php` (17) and `AdminNewsletterTest.php` (15), including the query-count assertion |
| Documentation | ✅ [features/newsletter.md](features/newsletter.md), listed in `mkdocs.yml` `nav:` |

**Deviation: `confirm` and `unsubscribe` are `POST`, not the `GET` planned above.** The plan's verb
was MealHub's, where a mail client opened those URLs directly. The `FRONTEND_URL` indirection this
phase introduces removes that constraint — the emailed link lands on the SPA, which then calls the
API — so the verb is free to be the correct one for a state change, and is no longer triggerable by
anything that merely follows a link (a crawler, a link preview, a mail-client prefetch). Path and
token placement are unchanged.

**`FRONTEND_URL` landed as `config('app.frontend_url')`**, falling back to `app.url` when unset so a
same-origin deployment still emits an absolute link. This is what unblocks the two deferred link
features called out in Phases 1 and 6 — the dashboard link in the activation email, and any future
link-based flow. Neither was added here; they belong to the phases that own them.

**One id-taking route with no Policy, deliberately.** `DELETE v1/admin/newsletter/{subscriber}`
takes an id from the URL, which the Definition of Done normally answers with a Policy. A subscriber
has no owner to compare the caller against, so `role:admin` *is* the whole authorization question —
the same shape as Phase 10's CMS records. The rule that still binds: anything with an **owner**
needs a Policy.

**No `migrate:fresh --seed` was needed**: this phase adds no migration and no factory. The
`newsletter_subscribers` table, its factory and its four states all shipped with the data layer and
are already pinned by `FactoryIntegrityTest`.

**One thing worth copying next phase:** MealHub's named `newsletter` rate limiter existed mostly to
reshape the 429 body into its own JSON envelope. Phase 0's handler already does that for every
`api/*` route, including `retry_after`, so an inline `throttle:5,1` was enough and
`AppServiceProvider` went untouched.

---

## Phase 8 — Rider vehicle ✅ Complete

The first phase where the media foundation is reused by a domain other than the one that
built it. `php artisan test --compact`: 310 passed.

**Deviation: `RiderVehiclePolicy` was not built.** Nothing in this phase takes an id from the URL —
a rider has exactly one vehicle and reaches it through their own token, so every lookup is keyed on
`$request->user()` and the class would have had no route to bind to. The half the roadmap named
that *does* need it ("admin may read any") is Phase 11's read of a named rider, which takes an id
and must bring the Policy with it. Same reasoning as Phase 0's dropped `PasswordResetRepository`,
Phase 3's `SectionFeatureRepository` and Phase 4's deferred `MediaController@show`.

| Category | Work |
| --- | --- |
| Controllers | ✅ `Api/V1/Rider/VehicleController` — `show`, `save`, plus a net-new `image` streaming action |
| Services | ✅ `RiderVehicleService` — flat under `app/Services/`, not the `Services/Rider/` sub-namespace above. One class is not a sub-namespace, same call as `NewsletterService`. |
| Repositories | ✅ `RiderVehicleRepository`, rebased on `BaseRepository<RiderVehicle>` — `forRider()` and `updateOrCreateForRider()`. MealHub's `setActiveForRider()` was **not** ported: its only caller is Phase 11's approval toggle. |
| Models | ✅ `RiderVehicle` — plus `IMAGE_COLLECTION = 'vehicle'`, the leaf the owning role prefixes |
| FormRequests | ✅ `Rider/SaveVehicleRequest`, on Phase 4's `ValidatesUploadedImage`. MealHub's `failedValidation()` override was not ported — the handler in `bootstrap/app.php` already shapes 422. |
| Resources | ✅ `RiderVehicleResource` — see the image note below; `ResolvesImageUrl` does **not** apply |
| Policies | ⏸ `RiderVehiclePolicy` deferred to Phase 11 — see above |
| Routes | ✅ `GET`/`POST v1/rider/vehicle` and `GET v1/rider/vehicle/image` under `auth:sanctum` + `role:rider` |
| Notifications | ✅ `RiderVehicleUpdatedNotification` → every admin, flat under `app/Notifications/` and built on Phase 6's `Concerns/FormatsUserDetails` — the reuse that trait was kept for |
| Events | — |
| Seeders / Factories | ✅ `RiderVehicleFactory` (unchanged) |
| Feature Tests | ✅ `tests/Feature/Rider/VehicleTest.php` (36), including the role gate on all three routes |
| Documentation | ✅ [features/rider-onboarding.md](features/rider-onboarding.md), listed in `mkdocs.yml` `nav:` |

**Four decisions worth keeping:**

- **`is_active` is derived, not defaulted.** The roadmap's rule — it tracks `users.status`, flipped
  by an admin on approval, never by the rider — is enforced by the service computing it from the
  rider's status on every save. MealHub passed only the validated payload to `updateOrCreate`, so
  the column's `default(true)` stood: a rider registers with `status = false`, and their vehicle
  claimed to be live for an account that was not. Re-deriving on every save also stops an edit
  resurrecting the flag on a deactivated rider.
- **The vehicle photo is a private file, and the column finally has a writer.** MealHub's
  `SaveVehicleRequest` had no `image` field at all, so `rider_vehicles.image` was dead. A photo of a
  named person's registration plate is personal data, so it goes to `MediaPlacement::Personal` under
  `rider/vehicle/{variant}/{filename}` and comes back only through an authenticated stream — the
  same treatment as a profile picture, and the reason `ResolvesImageUrl` (hard-wired to the public
  `Cms` placement) cannot be used here.
- **That stream lives on the domain's controller, not `MediaController`.** CLAUDE.md's parenthetical
  names `MediaController` because it was the only private reader; keeping each domain's read next to
  the rules that produced the file is what Phase 9's planned `Restaurant/DocumentController@show`
  already assumes. `MediaController@show` stays the profile-picture path.
- **Uniqueness of a plate is validated, because the index does not cover it.** The migration's
  comment claims `[rider_id, registration_number]` stops two riders claiming the same plate; it does
  not — it only stops one rider holding it twice, which the upsert already prevents. The Form
  Request scopes a `unique` rule to *other* riders, so a collision is a 422 on the field while
  resubmitting your own unchanged plate stays a valid edit.

**No `migrate:fresh --seed` was strictly needed** — this phase adds no migration and no factory —
but it was run against MySQL anyway, since the phase writes the first non-avatar private files.

---

## Phase 9 — Restaurant documents

| Category | Work |
| --- | --- |
| Controllers | `Api/V1/Restaurant/DocumentController` — `save`, `show(slot)` (port) |
| Services | `Restaurant/RestaurantDocumentService` (port) |
| Repositories | `RestaurantRepository` (partial, completed in Phase 11) |
| Models | ✅ `users.doc_image1` / `doc_image2` already migrated |
| FormRequests | `Restaurant/SaveDocumentRequest` (port) |
| Resources | `RestaurantDocumentResource` |
| Policies | `RestaurantDocumentPolicy` — owner and admin only. These are **private** documents and must not sit on a public disk — store them through `MediaPlacement::Personal` and stream them from Phase 5's `MediaController`. Note its existing `show()` serves the caller's own avatar and takes no id; the admin read path takes one, so it needs a sibling action **and** the Policy that comes with an id in the URL. |
| Routes | `v1/restaurant/documents*` (`role:restaurant`), `v1/admin/restaurants/{user}/documents/{slot}` (`role:admin`) |
| Notifications | `RestaurantDocumentUpdatedNotification` → admin (port) |
| Events | — |
| Feature Tests | `tests/Feature/Restaurant/DocumentTest.php` — port `RestaurantDocumentTest`, add 403 for another restaurant and 401 unauthenticated |
| Documentation | New `docs/features/restaurant-documents.md` |

---

## Phase 10 — Admin CMS write

The largest phase: 8 resources × (index, store, update, destroy, toggle) ≈ 36 endpoints.

| Category | Work |
| --- | --- |
| Controllers | `Api/V1/Admin/Cms/{SiteSetting,NavMenu,HomeStat,HomeSection,SectionFeature,MealCategory,FeaturedRestaurant,Testimonial}Controller`. Use an abstract `BaseCmsController` for the shared index/store/update/destroy/toggle shape — the same technique as `BaseAuthController`. |
| Services | Eight `Cms/*Service` classes (port). `HomeSectionService` and `SiteSettingService` are the non-uniform ones — read them before assuming the base pattern fits. |
| Repositories | `Cms/*Repository` × 8 — extend the Phase 3 read-only versions with write methods |
| Models | ✅ |
| FormRequests | Port all eight `Cms/Save*Request` / `Update*Request`, rebased on Phase 4's shared image trait |
| Resources | Reuse Phase 3's. Do not write admin variants unless they genuinely expose different fields. |
| Policies | — (`role:admin` suffices; CMS records have no per-record ownership) |
| Routes | `v1/admin/cms/*` under `auth:sanctum` + `role:admin`. Use real REST verbs — MealHub's `POST .../{id}/delete` is a Blade-form workaround, not a design choice. |
| Notifications / Events | — |
| Feature Tests | `tests/Feature/Cms/Admin*Test.php` — port MealHub's eight admin CMS tests. Each needs happy path, validation failure, 403 for non-admin, 404, and a toggle round-trip. |
| Documentation | Extend `docs/features/home-cms.md` with the admin surface |

**Suggested split:** ship `SiteSetting` and `Testimonial` first as pattern-setters, review, then
batch the remaining six.

---

## Phase 11 — Admin user management

| Category | Work |
| --- | --- |
| Controllers | `Api/V1/Admin/{Customer,Restaurant,Rider}Controller` — `index`, `show`, `toggleStatus` (port) |
| Services | Port MealHub's three management services; evaluate collapsing them into one `UserManagementService` scoped by role, on the same argument as `AuthService` |
| Repositories | `CustomerRepository`, `RestaurantRepository`, `RiderRepository` (port) |
| Models | ✅ `User` |
| FormRequests | `Admin/ListUsersRequest` (net-new: filter, sort, paginate parameters) |
| Resources | `AdminUserResource` — genuinely different from `UserResource`: exposes status, documents, vehicle, verification state |
| Policies | — (`role:admin`) |
| Routes | `v1/admin/{customers,restaurants,riders}` plus `{user}/toggle-status` |
| Notifications | `AccountStatusNotification` (consolidated in Phase 6) fires on toggle |
| Events | `UserStatusChanged` (Phase 6) — toggling a rider's status must also flip `RiderVehicle.is_active` |
| Feature Tests | Port `AdminCustomerManagementTest` and `AdminRestaurantManagementTest`, add the rider equivalent. Assert the notification dispatch and the rider-vehicle side effect. |
| Documentation | New `docs/features/admin-user-management.md` |

---

## Phase 12 — Dashboards

Last, because every widget reads data the earlier phases create.

| Category | Work |
| --- | --- |
| Controllers | `Api/V1/DashboardController@index` — role-switched payload, replacing MealHub's four dashboard controllers |
| Services | Keep all four of MealHub's dashboard services — these genuinely differ per role |
| Repositories | Reuse the aggregate queries from earlier phases' repositories |
| Models | ✅ |
| FormRequests | — |
| Resources | One `DashboardResource` per role, or one with role-conditional sections |
| Policies | — |
| Routes | `GET v1/dashboard` — `auth:sanctum`, payload determined by the token's role |
| Notifications / Events | — |
| Feature Tests | `tests/Feature/Dashboard/` — one per role, asserting counts against seeded and factory data, **plus a query-count assertion** |
| Documentation | New `docs/features/dashboards.md` |

Dashboards are the classic N+1 offender. Assert query counts rather than trusting review.

---

## Phase 13 — Terms & conditions (net-new, backlog)

`TermCondition`, `TermConditionUser`, their migrations and `scopeActiveForRole()` are ported, but
**MealHub has no T&C endpoints** — registration only sets the `accept_registration_tnc` boolean.
So this is new construction, not a port: a public "active terms for role" read, acceptance
recording (`accepted_at`, `ip_address`), and admin versioning CRUD. Nothing blocks it; schedule
it when the product needs it.

---

## Definition of Done

No phase is complete until every box is ticked.

**Architecture**

- [ ] Controllers contain no business logic — validate, delegate, respond
- [ ] Services contain no direct `Model::query()` — all access via repositories
- [ ] Repositories contain no business rules
- [ ] Every validated action has its own Form Request (no inline `$request->validate()`)
- [ ] Every model or collection response passes through an API Resource
- [ ] Existing services, scopes and traits were searched before writing new ones

**Security**

- [ ] Every route carries the correct middleware (`auth:sanctum` plus `role:*` where applicable)
- [ ] Ownership is verified by a Policy wherever an ID arrives from the URL
- [ ] A test asserts 403 for a token of the wrong role

**Tests**

- [ ] Each endpoint covers happy path, validation failure, auth failure, and not-found
- [ ] `php artisan test --compact` is green
- [ ] Test data comes from factories, not inline attribute overrides
- [ ] `php artisan migrate:fresh --seed` run against **MySQL** — SQLite ignores varchar limits

**Quality and documentation**

- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `docs/features/<name>.md` created or updated (`/project-docs`)
- [ ] `php artisan docs:generate --check` passes
- [ ] No N+1 — query counts asserted on list and dashboard endpoints
- [ ] One phase, one commit (`/commit-push`)

---

## Dependencies, added just in time

| Package | Phase | Why |
| --- | --- | --- |
| `intervention/image` ✅ | 4 | Image resize and optimization — installed at 3.11.8 |
| `stripe/stripe-php` | — | No payment routes exist in MealHub today |
| `barryvdh/laravel-dompdf` | — | No PDF routes exist in MealHub today |
| `laravel/socialite` | — | No social-login routes exist in MealHub today |
| `firebase/php-jwt` | never | Sanctum replaces it; do not reintroduce JWT |

Three of MealHub's five extra packages back **no current routes** — they are installed for
planned work. Do not port them on the assumption they are in use.
