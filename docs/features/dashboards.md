# Dashboards

What a signed-in user sees when they land: who they are, how many notifications are waiting, and — depending on their role — how far through onboarding they are or how many accounts they administer. One route, four payloads.

**Status:** ✅ Implemented — [roadmap](../roadmap.md) Phase 12.

## Endpoints

| Method | Path | Auth |
| --- | --- | --- |
| GET | `/api/v1/dashboard` | `auth:sanctum` |

**No `role:` gate, and no Policy.** The route joins `v1/profile*` and `v1/notifications` as one every role may call for itself: a gate listing all four roles would gate nothing. What makes that safe is that the action takes **no id** — the payload is assembled from `$request->user()`, so there is no other account any caller can reach. The role is read off the authenticated user, never off the request, so nothing a client sends selects a different payload.

## Request / Response

- Validated by: — (no input; the route takes no parameters and no body)
- Shaped by: `App\Http\Resources\DashboardResource`
- Business logic: `App\Services\DashboardService`
- Data access: `App\Repositories\UserRepository`, `App\Repositories\NotificationRepository`, `App\Repositories\RiderVehicleRepository`
- Controller: `Api\V1\DashboardController`

Every role gets the same three keys — `role`, `user`, `notifications` — plus at most one role-shaped section merged in alongside them:

| Role | Extra key | Contents |
| --- | --- | --- |
| `customer` | — | none |
| `restaurant` | `onboarding` | `account_activated`, and `documents` (a nested `RestaurantDocumentResource`) |
| `rider` | `onboarding` | `account_activated`, `vehicle_registered`, and `vehicle` (a nested `RiderVehicleResource` or `null`) |
| `admin` | `users` | `customer` / `restaurant` / `rider`, each `{total, active, inactive}` |

`user` is an **identity block, not a profile**: id, name, email, role, the two status flags, and `image_url`. Like `UserResource`'s, that `image_url` is the address of the authenticated streaming endpoint (`v1/media/profile-picture`) rather than a storage URL, and `null` when there is no picture — the stored filename never ships.

## Business Rules & Edge Cases

- **This is not a second profile endpoint.** Nearly everything the reference app's dashboards render is the profile pane, which `GET /api/v1/profile` already serves. Address, ZIP, coordinates and the hydrated country/county/city rows are deliberately **absent** here — duplicating them would mean two endpoints to keep in step every time the profile grows a field. A feature test asserts each of those keys stays missing, because an absence is only enforceable if something checks for it.
- **A customer has no role section at all.** Their only gate is email verification, which every role already reports on `user`; an `onboarding` block for them would restate one boolean under a second name. An absent key is a clearer answer than a `null` a client has to learn is structural — the same call `AdminUserResource` makes for `documents` and `vehicle`.
- **`account_activated` rather than `is_verified`.** A restaurant and a rider pass two independent gates: an admin flipping `users.status`, and the email verification that lives on `user`. One key named "verified" covering both would be read as whichever the client happened to mean.
- **The admin tally counts the three managed roles, never admins.** `DashboardService::COUNTED_ROLES` is exactly the three collections `v1/admin/{customers,restaurants,riders}` lists, so every tile leads somewhere — and an admin is not somebody an admin manages, which is the same reason Phase 11 made admin rows unreachable through those lists.
- **A role with no accounts ships a tile at zero, not a missing key.** `UserRepository::countsByRole()` returns only roles that have rows; filling the gaps is the service's job, because "which tiles exist" is a decision about the dashboard rather than about the table. Otherwise a fresh install would render two boxes instead of three.
- **The tally is one grouped query for all three roles**, not one per role and not one per account — `GROUP BY role` with a `SUM(CASE WHEN status = 1 …)` split. MySQL returns that sum as a string, so the service casts it; the repository uses `toBase()` because these are aggregates, and hydrating a `User` per row would produce model instances whose only real attribute is `role`.
- **Cost is fixed at two queries for every role**, and one of them is the unread badge. A customer's and a restaurant's sections cost nothing extra — email verification and the two document columns are already on the row the token resolved. Two feature tests measure it across a 30× difference in row count.
- **The nested Resources are reused, not re-implemented.** `RestaurantDocumentResource` already computes `is_complete` and emits the right download address per caller; `RiderVehicleResource` already emits the rider's own photo endpoint rather than the admin one. Neither needed a dashboard variant.
- **One role-switched service replaces the reference app's four.** Its `AdminDashboardService`, `CustomerDashboardService`, `RestaurantDashboardService` and `RiderDashboardService` were three `findById` wrappers and one that added two derived booleans — a `match` arm, not a class. The roadmap predicted they "genuinely differ per role"; they did not. Same collapse `AuthService`, `ProfileService` and `UserManagementService` already made.

## Tests

`tests/Feature/Dashboard/DashboardTest.php` (26) — the shared block per role, the profile keys asserted absent, the unread badge scoped to the caller, each role's section including both the empty and the populated state, the picture address, admin tally including the zero-fill and the admin exclusion, 401 for an anonymous and for a revoked token, and two query-count assertions.

Both cost measurements issue a **warm-up request before enabling the query log**. The sanctum guard resolves the token, loads the user and stamps `last_used_at` on the first authenticated request of a test method and then memoizes — so a baseline taken there measures three queries of auth handshake the comparison request never repeats, and the endpoint appears to get *cheaper* as rows are added.
