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
                    └─→ 4. Media foundation ✅┬─→ 5. Profile
                                              ├─→ 8. Rider vehicle
                                              ├─→ 9. Restaurant documents
                                              └─→ 10. Admin CMS write
                         6. Notifications ────┴─→ 11. Admin user mgmt ─→ 12. Dashboards
                         7. Newsletter
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
| `NotificationRepository` | 6 |
| `NewsletterSubscriberRepository` | 7 |
| `RiderVehicleRepository` | 8 |
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

## Phase 5 — Profile (all four roles)

| Category | Work |
| --- | --- |
| Controllers | `Api/V1/ProfileController` — one controller, role read from the token, replacing MealHub's four near-identical `ProfileController`s |
| Services | `ProfileService`. Compare MealHub's four role-specific profile services first; if real differences exist keep them as private methods, not four classes. |
| Repositories | `UserRepository` (extend) |
| Models | ✅ `User` |
| FormRequests | `Profile/UpdateProfileRequest`, `Profile/UpdateProfilePictureRequest`. Drop MealHub's `UpdateProfileWithPictureRequest` — an API separates the two calls. |
| Resources | Extend `UserResource`: avatar URL via the Phase 4 trait, nested country/county/city from Phase 2 |
| Policies | — (self-scoped: always `$request->user()`, never an ID from the request) |
| Routes | `GET v1/profile`, `PUT v1/profile`, `POST v1/profile/picture` — `auth:sanctum`, not role-gated. **Plus Phase 4's deferred `GET v1/media/...`**: avatars are personal data on the private disk, so this phase is where the authenticated streaming controller is finally needed and must be built. |
| Notifications / Events | — |
| Feature Tests | `tests/Feature/Profile/` — update happy path, validation failure, picture upload and replace, 401 unauthenticated, and a test that user A cannot mutate user B |
| Documentation | New `docs/features/profile.md` |

---

## Phase 6 — Notifications

| Category | Work |
| --- | --- |
| Controllers | `Api/V1/NotificationController` — `index`, `unread`, `markAsRead`, `markAllAsRead`, `toggleRead`, `destroy`. One controller replaces MealHub's four identical per-role ones (24 routes → 6). |
| Services | `NotificationService` (port, merging `Admin/AdminNotificationService` and `Customer/CustomerNotificationService`) |
| Repositories | `NotificationRepository` (port) |
| Models | ✅ `notifications` migration ported; uses Laravel's `DatabaseNotification`, no custom model |
| FormRequests | — |
| Resources | `NotificationResource` plus a paginated collection — settle the pagination envelope here (Phase 0.2), it recurs through Phases 10–12 |
| Policies | `NotificationPolicy` — owner-only. The first real policy in the codebase; `{id}` comes from the URL, so ownership must be enforced. |
| Routes | `v1/notifications/*` under `auth:sanctum` |
| Notifications | Port and consolidate: `{Customer,Restaurant,Rider}RegistrationNotification` → one role-parameterized class; same for `AccountStatusNotification`. Keep the `FormatsUserDetails` trait. |
| Events | Recommended (net-new): `UserStatusChanged` + listener, decoupling Phase 11's toggle from mail sending |
| Feature Tests | `tests/Feature/Notifications/` — list and unread counts, mark-read idempotency, 403 on another user's notification, pagination |
| Documentation | New `docs/features/notifications.md` |

---

## Phase 7 — Newsletter

| Category | Work |
| --- | --- |
| Controllers | `Api/V1/NewsletterController` — `subscribe` (public), `confirm(token)`, `unsubscribe(token)`; `Api/V1/Admin/NewsletterController` — `index`, `destroy` |
| Services | `Newsletter/NewsletterService` (port) |
| Repositories | `NewsletterSubscriberRepository` (port) |
| Models | ✅ `NewsletterSubscriber` — double opt-in, with `status` / `is_mailable` derived from the two timestamps and never stored. Preserve this. |
| FormRequests | `Newsletter/SubscribeRequest` (port) |
| Resources | `NewsletterSubscriberResource` |
| Policies | — (admin routes use the Phase 1 `role:admin` middleware) |
| Routes | `POST v1/newsletter/subscribe` (public, throttled), `GET v1/newsletter/confirm/{token}`, `GET v1/newsletter/unsubscribe/{token}`, `v1/admin/newsletter*` |
| Notifications | `NewsletterConfirmationNotification` (port) |
| Events | — |
| Feature Tests | `tests/Feature/Newsletter/` — subscribe, duplicate email, confirm with valid/invalid/expired token, unsubscribe, admin list and delete. Port MealHub's `NewsletterSubscriptionTest`. |
| Documentation | New `docs/features/newsletter.md` |

**SPA constraint:** confirm and unsubscribe links land in an email. They must point at the React
app, which then calls the API — pointing them at the API directly shows the user raw JSON.
Introduce a `FRONTEND_URL` env value in this phase.

---

## Phase 8 — Rider vehicle

| Category | Work |
| --- | --- |
| Controllers | `Api/V1/Rider/VehicleController` — `show`, `save` (port) |
| Services | `Rider/RiderVehicleService` (port) |
| Repositories | `RiderVehicleRepository` (port) |
| Models | ✅ `RiderVehicle`; `User::vehicles()` relation already added |
| FormRequests | `Rider/SaveVehicleRequest` (port) |
| Resources | `RiderVehicleResource` (Phase 4 image trait) |
| Policies | `RiderVehiclePolicy` — rider owns their vehicle; admin may read any |
| Routes | `v1/rider/vehicle` under `auth:sanctum` + `role:rider` |
| Notifications | `RiderVehicleUpdatedNotification` → admin (port) |
| Events | — |
| Seeders / Factories | ✅ `RiderVehicleFactory` |
| Feature Tests | `tests/Feature/Rider/VehicleTest.php` — port `RiderVehicleFlowTest`, add role-gate and cross-rider 403 |
| Documentation | New `docs/features/rider-onboarding.md` |

**Business rule to preserve:** `is_active` tracks `users.status`, flipped by an admin on approval
(Phase 11) — never by the rider.

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
| Policies | `RestaurantDocumentPolicy` — owner and admin only. These are **private** documents and must not sit on a public disk — store them through `MediaPlacement::Personal` and serve them through the streaming endpoint Phase 5 builds. |
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
