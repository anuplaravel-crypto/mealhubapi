# Admin User Management

The admin's view of everybody else's account: three lists, three profile reads, and the one write an admin has over another person's row — the activation gate that decides whether a restaurant may receive orders, a rider may accept jobs, or a customer may order at all.

**Status:** ✅ Implemented — [roadmap](../roadmap.md) Phase 11. This is the phase that finally gives `UserStatusChanged` and `AccountStatusNotification` (built in Phase 6) a producer, and the phase that supplies the `RiderVehicle.is_active` writer Phase 8 deliberately left out.

## Endpoints

| Method | Path | Auth |
| --- | --- | --- |
| GET | `/api/v1/admin/customers` | `auth:sanctum` + `role:admin` |
| GET | `/api/v1/admin/customers/{id}` | `auth:sanctum` + `role:admin` |
| PATCH | `/api/v1/admin/customers/{id}/toggle-status` | `auth:sanctum` + `role:admin` |
| GET | `/api/v1/admin/restaurants` | `auth:sanctum` + `role:admin` |
| GET | `/api/v1/admin/restaurants/{id}` | `auth:sanctum` + `role:admin` |
| PATCH | `/api/v1/admin/restaurants/{id}/toggle-status` | `auth:sanctum` + `role:admin` |
| GET | `/api/v1/admin/riders` | `auth:sanctum` + `role:admin` |
| GET | `/api/v1/admin/riders/{id}` | `auth:sanctum` + `role:admin` |
| PATCH | `/api/v1/admin/riders/{id}/toggle-status` | `auth:sanctum` + `role:admin` |
| GET | `/api/v1/admin/riders/{rider}/vehicle/image` | `auth:sanctum` + `role:admin` + `UserPolicy@viewVehicle` |

The three sets are registered from one closure in `routes/api.php`, with the path segment doubling as the route-name segment (`admin.customers.index`, `admin.riders.toggle-status`). A restaurant's identity documents stream from the Phase 9 route, `GET /api/v1/admin/restaurants/{restaurant}/documents/{slot}` — see [restaurant-documents.md](restaurant-documents.md).

**There is no admin create, edit or delete of an account.** An admin does not sign someone else up — registration is the person's own act, through their role's OTP flow — and a deleted account would take its orders, documents and vehicle with it. Activation is the whole write surface.

## Request / Response

- Validated by: `App\Http\Requests\Admin\ListUsersRequest` (list query parameters only; the reads and the toggle take no body)
- Shaped by: `App\Http\Resources\Admin\AdminUserResource`, nesting `RestaurantDocumentResource` and `RiderVehicleResource`
- Business logic: `App\Services\UserManagementService`
- Data access: `App\Repositories\UserRepository`, `App\Repositories\RiderVehicleRepository`
- Controllers: `Api\V1\Admin\BaseUserManagementController` + `CustomerController` / `RestaurantController` / `RiderController`, and `Api\V1\Admin\RiderVehicleController` for the photo stream

### List parameters

| Parameter | Values | Default |
| --- | --- | --- |
| `search` | free text, matched against `firstName`, `lastName`, `email`, `mobile` | — |
| `status` | `1` / `0` (`true` / `false`) | absent means both |
| `sort` | `created_at`, `updated_at`, `firstName`, `lastName`, `email`, `status` | `created_at` |
| `direction` | `asc`, `desc` | `desc` |
| `per_page` | 1–100 | 20 |

Lists go through `paginatedResponse()`, so rows are at `data` and the paginator state at `meta` — see [api-conventions.md](api-conventions.md).

### The admin's view of an account

`AdminUserResource` is a separate class from `UserResource` rather than a flag on it, because the two answer different questions. `UserResource` answers *"what is my account?"* for its owner; this answers *"should I activate this account?"* for somebody else.

- **No `image` and no `image_url`.** A profile picture is a private file and `v1/media/profile-picture` serves only the token holder's own, so an admin has no address to be given — and emitting the stored filename instead would put a private storage name in a response, which the [media rules](media-uploads.md) forbid outright. `has_profile_picture` is the whole of what an admin can act on.
- **The verification material is role-conditional.** A restaurant carries `documents` (the `RestaurantDocumentResource` payload, with admin download addresses for the filled slots); a rider carries `vehicle` (the `RiderVehicleResource` payload, with the admin photo address). Neither key appears on a role that has no such thing — an absent key is clearer than a null a client has to know is structural.
- **`accept_registration_tnc` and `marketing_consent` are present**, because the record of what the person agreed to at signup is exactly what an account review is for.

## Business Rules & Edge Cases

- **The role is a scope, not a filter.** The four roles share one `users` table, so every query here is pinned to a role — and the role is fixed by the *controller class*, never read from the path or the query string. `admin/customers` cannot be widened into everybody by anything a client sends.
- **A wrong-collection id is a 404, not a 403.** `UserRepository::findByRoleOrFail()` scopes the lookup, so an id naming a rider simply does not exist under `admin/customers`. A 403 would confirm the row exists somewhere else; a 404 says only that it is not here.
- **An admin is not manageable through these routes.** No segment lists the `admin` role, so an admin id resolves nowhere — which is what stops an admin deactivating a colleague, or themselves, and locking the platform out of its own back office.
- **The ids on the nine management routes carry no Policy, and that is the considered call**, not the newsletter/CMS exception repeated. The rule in `CLAUDE.md` is that a Policy verifies *ownership* wherever an id arrives from the URL, and there is no ownership here — an admin does not own a customer. The two questions authorization must answer are *is the caller an admin* (`role:admin`) and *does the id name an account of this role* (the scoped lookup, which never finds anything else). An ability could only re-read the same column after a wider query had already found the row.
- **The tenth route does carry a Policy, because it hands over a file.** `GET admin/riders/{rider}/vehicle/image` binds a `User` and streams a photograph of a named person's registration plate off the private disk, so `UserPolicy::viewVehicle()` refuses any id that does not name a rider — the half `role:admin` cannot do. This is the ability the roadmap deferred out of Phase 8, where nothing took an id and there was no route to bind it to. It lives on `UserPolicy` rather than a separate `RiderVehiclePolicy` for the reason recorded in Phase 9: policy discovery maps `App\Models\User` to `App\Policies\UserPolicy`, and a second class would need an explicit `Gate::policy()` registration.
- **The toggle sends no target state.** `PATCH .../toggle-status` flips whatever is there, so a replayed request cannot reactivate an account an admin had just suspended. The updated account comes back on the response, so the client reads the new state rather than assuming its own guess landed.
- **Everything that follows from a toggle hangs off `UserStatusChanged`, not off the toggle.** Two listeners are bound to it: `SendAccountStatusNotification` mails and stores the notice, and `SyncRiderVehicleStatus` points the rider's vehicle rows at the new status. Neither is a line in `UserManagementService`, which is why a customer toggle carries no rider concern. Verify the wiring with `php artisan event:list`.
- **`rider_vehicles.is_active` is derived at both ends.** `RiderVehicleService` computes it from `users.status` on every rider save, and this phase's listener updates it when an admin flips that status. The two together are what keep a vehicle from ever claiming to be live for an account that is not.
- **The toggle is not wrapped in a transaction, on purpose.** Two single-row updates in separate listeners would need one only if a half-applied result were unrecoverable, and it is not — a vehicle left behind is corrected the next time the rider saves. A transaction here would instead put mail delivery inside it, which is the failure mode that actually costs something: an email announcing a rollback.
- **The toggle re-reads the account before answering.** The listeners write through the database (`SyncRiderVehicleStatus` updates the vehicle rows in one statement), so the relations loaded a moment earlier are stale — a response built from them would report a rider's vehicle as still active after deactivating them.
- **A search term is not a pattern.** `%` and `_` in `search` are escaped before reaching `LIKE`, so a search box cannot be used to dump the table.
- **`sort` is whitelisted, and that whitelist is load-bearing.** It reaches `orderBy()` as a column name, so `Rule::in()` in `ListUsersRequest` is the only thing between a query parameter and an identifier in SQL. It is a whitelist rather than a "is this a real column" check because `password`, `otp` and `remember_token` are real columns too, and ordering by one leaks its ordering.
- **`per_page` is capped at 100.** An unbounded page size turns a paginated endpoint back into the full-table read this replaced — the reference app rendered every account into a client-side DataTable, which stops being an option the moment the list is JSON.
- **Every list eager-loads what its rows render** — the three location relations for all roles, plus `vehicles` for riders — so a page costs a fixed number of queries regardless of size.

## Architecture Notes

**One role-parameterized service replacing the reference app's three.** `CustomerManagementService`, `RestaurantManagementService` and `RiderManagementService` differed in exactly two places — the role string they scoped by, and whether a toggle also touched a vehicle — and both of those are arguments, not classes. `AuthService` and `ProfileService` made the same call.

**One repository, not three.** MealHub's `CustomerRepository` / `RestaurantRepository` / `RiderRepository` were three classes querying the same table with the same four methods. `app/Repositories/` holds one class per *model*, so those queries live on `UserRepository`.

**Three controllers, not one.** The role must not be something a request can choose, so it is fixed by the class. `BaseUserManagementController` holds every action's body and each subclass supplies only its role and the noun for its messages — the same technique as `BaseAuthController` and `BaseCmsController`. Ids arrive as plain integers rather than through route-model binding for the reason `BaseCmsController` records: binding resolves from the concrete method's type hint, so a shared action could not declare one.

## Tests

`tests/Feature/Admin/UserManagementTest.php` (49) — each role's list and its scoping (including that no list ever holds an admin), search across all four searchable columns, wildcard escaping, the status filter in both directions and absent, sorting and paging, rejection of an unsafe `sort`/`direction`/`per_page`, a fixed query count on the rider list, profile reads for all three roles with their documents and vehicle payloads, the profile never naming the stored picture, a cross-role or unknown id 404ing on both read and toggle, the toggle in both directions with its message and its persisted effect, the event dispatch, the notification and its channels, a rider's vehicle following the toggle both ways and other riders' vehicles staying put, a rider without a vehicle toggling cleanly, the vehicle photo streaming at its default and an explicit variant, the Policy refusing a non-rider id, a 404 with no photo, and 401 plus one 403 case per non-admin role on every endpoint.

Tests mint real tokens and send a `Bearer` header, and use one data-provider case per role rather than a loop: the sanctum guard memoizes the resolved user for the lifetime of a test method, so a second request as a different user would still be seen as the first. The same memoization is why the query-count test issues a warm-up request before measuring — comparing a cold request against a warm one would report the token lookup as an N+1.
