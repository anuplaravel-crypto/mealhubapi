# Notifications

In-app notifications — the list, the unread badge, and the read/delete actions behind them — for all four roles through one set of endpoints. This is also where the API's operational notification classes live: the admin-facing "somebody registered" notice, and the user-facing "an admin activated your account" notice.

**Status:** ✅ Implemented — [roadmap](../roadmap.md) Phase 6.

Storage is Laravel's own `notifications` table and its `DatabaseNotification` model; there is no custom model. Delivery is `['mail', 'database']` on both notification classes, so every operational notice is both emailed and readable in-app.

## Endpoints

| Method | Path | Auth |
| --- | --- | --- |
| GET | `/api/v1/notifications` | `auth:sanctum` |
| GET | `/api/v1/notifications/unread` | `auth:sanctum` |
| PATCH | `/api/v1/notifications/read-all` | `auth:sanctum` |
| PATCH | `/api/v1/notifications/{notification}/read` | `auth:sanctum` + `NotificationPolicy@update` |
| PATCH | `/api/v1/notifications/{notification}/toggle-read` | `auth:sanctum` + `NotificationPolicy@update` |
| DELETE | `/api/v1/notifications/{notification}` | `auth:sanctum` + `NotificationPolicy@delete` |

**No `role:` gate, deliberately** — the same reasoning as [profile](profile.md): every role has notifications, so a gate naming all four would gate nothing. What is different here is that four of the six take an id, which is why this domain brings the codebase's **first Policy**.

MealHub carried four copies of this controller — one per role area, 24 routes doing six distinct things — because each lived behind its own guard and Blade layout. The role arrives on the token here, so there is one copy and six routes.

## Request / Response

- Validated by: — (no endpoint takes a body; see "no client-controlled page size" below)
- Shaped by: `App\Http\Resources\NotificationResource`
- Business logic: `App\Services\NotificationService`
- Data access: `App\Repositories\NotificationRepository`
- Authorization: `App\Policies\NotificationPolicy`, registered in `AppServiceProvider`
- Controller: `Api\V1\NotificationController`

`GET /notifications` is a [paginated response](api-conventions.md) — rows in `data`, paginator state in `meta`. `GET /notifications/unread` is a plain success response wrapping `{count, notifications}`. The two read actions answer with the updated `NotificationResource`; `read-all` answers `{marked: n}`; `DELETE` answers `204` with an empty body.

One notification looks like this:

```json
{
  "id": "9f1b0c2e-…",
  "type": "customer_registration",
  "title": "New customer registration",
  "message": "Ada Lovelace just registered as a customer.",
  "data": { "type": "customer_registration", "role": "customer", "user_id": 12, "user": { … } },
  "read": false,
  "read_at": null,
  "created_at": "2026-07-21T09:14:22.000000Z"
}
```

Three shaping decisions worth knowing:

- **`type` is the semantic token from the stored payload, not the notification class.** The `notifications.type` column holds `App\Notifications\RegistrationNotification` — an internal class name of the kind the exception handler already works to keep out of responses, and one that would break every client the day a class is renamed. A test asserts no `App\Notifications` string reaches the wire.
- **`title` and `message` are lifted out of `data`, and `data` still ships whole.** Any notification renders from those two whatever kind it is; clients that switch on `type` read the kind-specific extras (`user`, `activated`) from `data`. MealHub's per-role services each lifted a *different* set of keys — that is precisely what one Resource replaces.
- **Timestamps are ISO 8601, not `diffForHumans()`.** "2 hours ago" is a rendering and localisation decision the client owns — the same reasoning that keeps CSS classes and route names out of the CMS resources.

## Business Rules & Edge Cases

- **Ownership is a 403, not a silent no-op.** MealHub scoped every lookup to the notifiable, so acting on somebody else's id quietly matched nothing and still answered "success". Here the row is resolved by route-model binding and `NotificationPolicy` decides — a real id belonging to somebody else is `403`, an id that does not exist is `404` (`Resource not found.`, from the handler's `NotFoundHttpException` remap).
- **The Policy compares `notifiable_type` as well as `notifiable_id`.** The table is polymorphic and ids collide across notifiable models. There is only one notifiable today, which is exactly why the check is easy to omit now and expensive to add back later.
- **Admins get no override.** Reading another user's notifications is not a feature anything asks for; if it ever arrives it comes as a separate `role:admin` route, not as a hole in this Policy.
- **The policy must be registered by hand.** Laravel discovers policies by mapping `App\Models\X` to `App\Policies\XPolicy`, and `DatabaseNotification` is in neither namespace — `Gate::policy(...)` in `AppServiceProvider` is what binds them. Without it every id-taking endpoint 403s, since `Gate::authorize()` denies an ability it cannot resolve.
- **Marking as read is idempotent and does not move `read_at`.** A retrying client must not overwrite when the user actually read something — that timestamp is the only thing it is good for. `toggle-read` is the separate action, and its unread direction is how a user re-flags something they opened by accident.
- **`read-all` is one `UPDATE`.** Laravel's `$user->unreadNotifications->markAsRead()` loads the whole unread set and saves row by row; the repository issues a single statement and returns the affected count, which the response reports as `marked`.
- **The unread endpoint counts everything and returns at most 20.** The badge shows the true total; the dropdown shows a slice. They are two different questions, so they are two different queries.
- **No client-controlled page size.** `GET /notifications` is fixed at 15 rows. A `?per_page=` would be validated input and this phase has no Form Request — Phase 11's `Admin/ListUsersRequest` is where paging parameters get one.
- **Ordering comes from the relation.** `Notifiable::notifications()` is a `morphMany(...)->latest()`, so the repository adds no `latest()` of its own — doing so only emitted a duplicate `order by`.
- **Reading the list is a flat 5 queries** regardless of row count: three are Sanctum's (token, user, the `last_used_at` touch) and two are the paginator's.

### The two consolidated notification classes

- **`RegistrationNotification`** replaces MealHub's `{Customer,Restaurant,Rider}RegistrationNotification`, which differed only in a noun and in one clause. It is raised by `AuthService::register()` and fanned out to every admin via `UserRepository::admins()` — there is no "the admin" row. Restaurants and riders are described as *awaiting verification* because they cannot work until an admin approves them; a customer can order as soon as they verify their email. **An admin registering another admin raises nothing** — that is not news to anybody, and it would notify the creator about themselves.
- Its stored keys are `role` / `user_id` / `user` rather than MealHub's per-role `customer_id` / `rider` / …, which existed only because a different popup script read each one.
- **Admins are told at signup, not at OTP verification.** A restaurant or rider is waiting on an admin; the queue an admin works from should not depend on whether the applicant has opened their email yet.
- **`AccountStatusNotification`** replaces the three per-role status notices. Unlike the registration class it **reads the role off the notifiable** rather than taking it as an argument: it is always addressed to the user whose status changed, so an argument could only ever disagree with the recipient.
- **It carries no dashboard link.** MealHub's activation email ended with a button pointing at its own Blade route; a cross-origin SPA has no such URL until `FRONTEND_URL` lands in Phase 7 — the same reason `OtpNotification` sends a code rather than a link. The action button belongs there, not as a placeholder here.
- **Nothing dispatches it directly.** `App\Events\UserStatusChanged` is fired and `App\Listeners\SendAccountStatusNotification` sends it. **There is no producer yet** — Phase 11's admin toggle is the one that will fire the event. The seam exists now because MealHub's three management services each called `$user->notify(...)` inline, making "flip a flag" and "send mail" one statement, and Phase 11 has a *second* consequence to hang off the same moment (a deactivated rider's `RiderVehicle.is_active` follows their status). That is a second listener, not a second line in the toggle.
- The listener is bound by Laravel's discovery of `app/Listeners`, so there is no provider entry to keep in sync — and nothing would fail loudly if the class moved. `php artisan event:list` is the check, and a test asserts the binding.
- **`FormatsUserDetails`** is kept as a trait rather than folded into the one registration class: Phases 8 and 9 raise the same personal-details block for vehicle and document uploads.

## Tests

`tests/Feature/Notifications/NotificationTest.php` (29) — list per role, the lifted payload keys, no class name on the wire, newest-first ordering, pagination at 15, other users' rows excluded, the query count, an empty list as `[]`, unread count vs. the 20-row cap, mark-as-read and its idempotency, both toggle directions, `read-all` touching only the caller's rows and reporting zero when there is nothing to mark, delete, **403 on another user's notification for all three id-taking actions**, 404 for an unknown id, and 401 on every endpoint.

`tests/Feature/Notifications/AccountNotificationTest.php` (12) — a registration notifying every admin (one case per self-service role), the stored payload an admin list can render, a customer signup not described as awaiting verification, an admin signup notifying nobody, the notification arriving in the admin's own in-app list end to end, the event being what sends the status notice, the role-specific copy per recipient, and the listener being registered.

Both suites mint real tokens and send a `Bearer` header. The cross-user 403 cases seed the victim's notification directly rather than issuing a request as that user: the sanctum guard memoizes the resolved user for the lifetime of a test method, so a two-request version would pass for the wrong reason.

`tests/Feature/Database/FactoryIntegrityTest.php` covers `Database\Factories\DatabaseNotificationFactory` — the one factory whose model is the framework's, so it has no `HasFactory` and is instantiated with `DatabaseNotificationFactory::new()` rather than `Model::factory()`.
