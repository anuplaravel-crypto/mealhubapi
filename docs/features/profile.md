# Profile

A signed-in user maintaining their own account details — name, contact, address, location and picture — for all four roles through one set of endpoints.

**Status:** ✅ Implemented — [roadmap](../roadmap.md) Phase 5. This phase also builds the authenticated streaming endpoint deferred out of Phase 4, since profile pictures are the first private files the API stores.

## Endpoints

| Method | Path | Auth |
| --- | --- | --- |
| GET | `/api/v1/profile` | `auth:sanctum` |
| PUT | `/api/v1/profile` | `auth:sanctum` |
| POST | `/api/v1/profile/picture` | `auth:sanctum` |
| GET | `/api/v1/media/profile-picture` | `auth:sanctum` |

**No `role:` gate, deliberately.** Every role is entitled to maintain its own account, and none of these routes takes an id — the row acted on is always `$request->user()`. That is also why there is no Policy: there is no id from the URL to verify ownership of. Phase 9's admin read of somebody else's documents is the first route that will need one.

## Request / Response

- Validated by: `App\Http\Requests\Profile\UpdateProfileRequest`, `App\Http\Requests\Profile\UpdateProfilePictureRequest` (rules from the shared `Concerns\ValidatesUploadedImage`)
- Shaped by: `App\Http\Resources\UserResource`, nesting `CountryResource` / `CountyResource` / `CityResource`
- Business logic: `App\Services\ProfileService`, `App\Services\Media\ImageUploadService`
- Data access: `App\Repositories\UserRepository`
- Controllers: `Api\V1\ProfileController`, `Api\V1\MediaController`

`GET` and `PUT` answer with the whole `UserResource`; the picture upload answers with it too, so a client that just changed a photo gets the new `image_url` without a second call. `GET /api/v1/media/profile-picture` is not JSON — it streams image bytes from the private disk, and takes an optional `?variant=small|medium|large|original` (default `medium`).

Two `UserResource` fields behave differently from the rest and are worth knowing about:

- **`image_url` is the streaming endpoint's address, not a storage URL.** Profile pictures are personal data on a private disk, so there is no public URL to hand out. It is `null` when the user has no picture — and also when the resource describes somebody other than the caller, since that endpoint serves nobody else's file. `image` alongside it is the raw stored filename.
- **`country` / `county` / `city` are hydrated only where they were eager-loaded** — the profile endpoints. Elsewhere (registration, login) the key is present and `null`, and the three `*_id` fields are the answer. Resolving them inside the Resource would cost three queries per user on every list that later reuses it.

## Business Rules & Edge Cases

- **Email is not self-editable.** It identifies the account and anchors OTP verification, so changing it needs its own re-verification flow rather than a profile save. Neither are `role`, `status` or `is_email_verified`: `status` is the admin-approval gate Phase 11 flips, and nothing self-served may touch it. The Form Request whitelists the payload and `ProfileService::EDITABLE_FIELDS` whitelists it again, so a field added to the request later cannot silently become self-editable.
- **An id in the payload changes nothing.** There is no id parameter anywhere in this domain; the service is handed the token's `User`. A crafted `{"id": 7}` updates the caller's own row, as a test pins.
- **One service, not four.** MealHub had `Customer`, `Admin`, `Restaurant` and `RiderProfileService`, which a diff shows to be identical apart from the role directory pictures land in. That is an argument, not four classes — the same reasoning that keeps `AuthService` single and role-parameterized.
- **Pictures are stored per role**: `MediaPlacement::Personal` on the private disk under `{role}/profile/{variant}/{filename}`. The four roles share one `users` table, so without the role in the path the private tree is one flat pile of avatars.
- **Replacing a picture cleans up the old one.** The outgoing filename goes to `ImageUploadService::store(..., replacing:)`, which deletes its variants only after the new ones are written — a failure mid-encode must not leave the row pointing at a deleted image.
- **The picture is its own endpoint.** MealHub needed a third `UpdateProfileWithPictureRequest` because one Blade form submitted everything at once; splitting them means a photo change never carries along half-edited form fields, and makes an empty picture submit a 422 rather than a silent no-op.
- **An unknown `variant` degrades to `medium`** rather than 404ing on a path that was never written — variants arrive from a query string. Nothing about the variant is validated, because no value of it can fail.
- **No picture, or a row pointing at a missing file, is a 404** from `ProfileService::picturePath()` (a `DomainException`), never an empty 200 body.
- **Location ids are checked for existence, not for consistency.** A `city_id` from a different county than the `county_id` sent alongside it is accepted, matching `Auth\RegisterRequest` — the cascade only exists in the client's UI today. Tightening it belongs with a change to both, not to this endpoint alone.
- **Reading a profile is a fixed query count**, with the three location rows eager-loaded through `UserRepository::withLocation()`. That method re-queries on every call, so the response after a move cannot still describe the previous city.
- **`GET /api/user` was removed.** The Laravel skeleton's closure route returned a raw model, bypassing the Resource layer and the response envelope; `GET /api/v1/profile` supersedes it.

## Tests

`tests/Feature/Profile/ProfileTest.php` (17) — read, hydrated and empty location, query count, update happy path, the response reflecting the location just saved, non-editable fields ignored, another user's row untouched, missing required fields, unknown location id, over-length value, 401 on both verbs, and one case per role.

`tests/Feature/Profile/ProfilePictureTest.php` (18) — upload writing all four variants under the role's directory, nothing reaching the public disk, `image_url` pointing at the streaming endpoint, replacement removing every old variant, rejection of a non-image / oversize / empty payload, 401, streaming own picture, an explicit and an unknown variant, 404 with no picture, the endpoint never serving another user's file, and one case per role.

Both suites mint real tokens and send a `Bearer` header, and use one data-provider case per role rather than a loop: the sanctum guard memoizes the resolved user for the lifetime of a test method, so a second request as a different user would still be seen as the first.
