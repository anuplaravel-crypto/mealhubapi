# Rider Onboarding

The one step rider onboarding asks for beyond the account itself: the vehicle a rider delivers on, submitted for an admin to verify before the account goes live.

**Status:** ✅ Implemented — [roadmap](../roadmap.md) Phase 8. The admin side of it — reading a named rider's vehicle, and approving the rider — shipped in Phase 11; see [admin-user-management.md](admin-user-management.md).

## Endpoints

| Method | Path | Auth |
| --- | --- | --- |
| GET | `/api/v1/rider/vehicle` | `auth:sanctum` + `role:rider` |
| POST | `/api/v1/rider/vehicle` | `auth:sanctum` + `role:rider` |
| GET | `/api/v1/rider/vehicle/image` | `auth:sanctum` + `role:rider` |

**`role:rider` is a real gate here**, unlike on the profile and notification routes: a vehicle is not something every role has a version of, so the gate names one role rather than listing all four. A token from any other role is a 403, an absent token a 401.

**No route takes an id, so no Policy is needed.** A rider has exactly one vehicle and reaches it through their own token — every lookup is keyed on `$request->user()`, so there is no id from a request that could name another rider's row. The admin read of a *named* rider's vehicle — `GET /api/v1/admin/riders/{rider}/vehicle/image` — does take an id, and carries `UserPolicy@viewVehicle`.

## Request / Response

- Validated by: `App\Http\Requests\Rider\SaveVehicleRequest` (photo rules from the shared `Concerns\ValidatesUploadedImage`)
- Shaped by: `App\Http\Resources\RiderVehicleResource`
- Business logic: `App\Services\RiderVehicleService`, `App\Services\Media\ImageUploadService`
- Data access: `App\Repositories\RiderVehicleRepository`, `App\Repositories\UserRepository`
- Controller: `Api\V1\Rider\VehicleController`

`POST` is one endpoint for both create and update — the record is an upsert keyed on the rider, so "add" and "edit" are the same submission with the same fields. A first submission answers **201**, an edit **200**, so a client can distinguish "submitted for verification" from "sent back for re-verification" without parsing the message. It is POST rather than PUT for the edit case as well, because PHP populates no uploaded-file bag on a PUT body.

`GET /api/v1/rider/vehicle/image` is not JSON — it streams image bytes from the private disk, and takes an optional `?variant=small|medium|large|original` (default `medium`).

`RiderVehicleResource` mirrors `UserResource`'s treatment of a private image: **`image_url` is the streaming endpoint's address, not a storage URL**, and is `null` when there is no photo (or when the resource describes another rider, whose file that endpoint would refuse to serve). `image` alongside it is the raw stored filename. `rider_id` is deliberately absent — the only caller is the rider, who is not told their own id twice.

## Business Rules & Edge Cases

- **`is_active` mirrors `users.status` and is never the rider's to set.** The service derives it from the rider's account status on every save, so a rider awaiting approval cannot ship a live-looking vehicle and an edit cannot resurrect the flag on a deactivated account. An `is_active` in the payload is ignored. Only an admin approving the account flips it, and that reaches the vehicle rows through `App\Listeners\SyncRiderVehicleStatus` rather than through this service.
- **Every save notifies every admin**, first submission or edit alike, through `RiderVehicleUpdatedNotification` (mail + database). An edit invalidates the verification the previous details were approved under — a rider could otherwise be approved on one plate and quietly swap in another. The stored payload's `type` is `rider_vehicle_updated` and its `title` carries the verb (`submitted` / `updated`).
- **A plate belongs to one vehicle in the world.** The table's unique index is `[rider_id, registration_number]`, which only stops one rider holding the same plate twice — something the upsert already makes impossible. `SaveVehicleRequest` covers the half the index does not: a plate registered to *another* rider is a 422 on the field, while resubmitting your own unchanged plate stays a valid edit.
- **Every text field is required even though the columns are nullable.** A vehicle record exists so an admin can verify it, and one missing its plate or model verifies nothing.
- **The photo is optional, and an edit without one keeps the existing file.** A rider correcting a typo does not have to re-upload.
- **The photo is private.** It shows a named person's registration plate, so it is stored through `MediaPlacement::Personal` on the private disk under `rider/vehicle/{variant}/{filename}` — never the public disk — and comes back only through the authenticated streaming endpoint. Replacement goes through `ImageUploadService::store(..., replacing:)`, which deletes the old variants only after the new ones are written.
- **The photo streams from this controller, not `Api/V1/MediaController`.** That controller's `show()` is the profile-picture path. Private reads stay with the domain that owns the file — Phase 9's restaurant documents do the same — so the authorization question for each file sits next to the rules that produced it.
- **An unknown `?variant=` degrades to `medium`** rather than 404ing on a path that was never written; nothing about it is validated, because no value can fail.
- **A rider with no vehicle is a 404** (`You have not registered a vehicle yet.`), not a `null` body — the record genuinely does not exist. A vehicle with no photo, or a row pointing at a missing file, is likewise a 404 from the image endpoint.
- **The admin email carries no action link.** The SPA's route for a pending rider is not something this API knows, and `RegistrationNotification` — the other admin-facing notice, raised at the other end of the same onboarding — ends without one for the same reason.
- **One service, flat under `app/Services/`.** The roadmap's `Services/Rider/` is MealHub's path; the convention here is that a sub-namespace arrives when a domain has several classes, and this domain has one — the same call `NewsletterService` made.

## Tests

`tests/Feature/Rider/VehicleTest.php` (36) — read with and without a vehicle, the endpoint serving nobody but the caller, create (201) and edit (200) on the same row, admins notified on both, empty and unknown-`vehicle_type` payloads, a plate claimed by another rider versus resubmitting your own, `is_active` derived from account status and unsettable by the rider, a photo reaching the private disk only, `image_url`, replacement removing every old variant, an edit preserving the existing photo, non-image and oversize rejection, streaming an explicit and an unknown variant, 404 with no photo, 403 for each of the other three roles on all three routes, and 401 on all three.

Tests mint real tokens and send a `Bearer` header, and use one data-provider case per role rather than a loop: the sanctum guard memoizes the resolved user for the lifetime of a test method, so a second request as a different user would still be seen as the first.
