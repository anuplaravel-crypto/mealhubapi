# Restaurant Documents

The two identity documents a restaurant files during onboarding — a business licence and a photo ID — and the admin read that lets those be verified before the account is activated.

**Status:** ✅ Implemented — [roadmap](../roadmap.md) Phase 9. Approving the restaurant off the back of a verified document shipped in Phase 11 — see [admin-user-management.md](admin-user-management.md).

## Endpoints

| Method | Path | Auth |
| --- | --- | --- |
| GET | `/api/v1/restaurant/documents` | `auth:sanctum` + `role:restaurant` |
| POST | `/api/v1/restaurant/documents` | `auth:sanctum` + `role:restaurant` |
| GET | `/api/v1/restaurant/documents/{slot}` | `auth:sanctum` + `role:restaurant` |
| GET | `/api/v1/admin/restaurants/{restaurant}/documents/{slot}` | `auth:sanctum` + `role:admin` + `UserPolicy` |

The first three are self-scoped — they act on `$request->user()` and take no user id, so no Policy is involved. **The fourth is the first route in the codebase to name another user**, and therefore the first to need one.

`{slot}` is `1` (business licence) or `2` (photo identification), constrained with `whereNumber`. A slot number rather than a column name, because `doc_image1` is a schema detail no client should have to know.

## Request / Response

- Validated by: `App\Http\Requests\Restaurant\SaveDocumentRequest` (rules from `Concerns\ValidatesUploadedDocument`)
- Shaped by: `App\Http\Resources\RestaurantDocumentResource`
- Business logic: `App\Services\RestaurantDocumentService`, `App\Services\Media\ImageUploadService`
- Data access: `App\Repositories\UserRepository`
- Authorized by: `App\Policies\UserPolicy@viewDocuments` (admin read only)
- Controllers: `Api\V1\Restaurant\DocumentController`, `Api\V1\Admin\RestaurantDocumentController`

`GET` and `POST` return the same shape: `is_complete`, plus a `documents` array with one entry per slot carrying `slot`, `key`, `label`, `on_file`, `is_pdf` and `url`. **No filename and no storage path is ever emitted** — `url` is the address of the streaming endpoint the caller is entitled to use (their own for a restaurant, the admin one for an admin), or `null` when the slot is empty.

The two download routes are not JSON — they stream bytes from the private disk. Both take an optional `?variant=small|medium|large|original`; the restaurant's own defaults to `medium`, the admin's to `large`, because an admin is reading a licence number rather than glancing at a thumbnail. A PDF ignores the parameter and always serves the original.

`POST` accepts `doc_image1` and/or `doc_image2` as multipart file fields. It answers **201** while the paperwork was still incomplete and **200** once it was already complete and this is a correction — the same split as [rider onboarding](rider-onboarding.md), so a client can tell "entering verification" from "back for re-checking" without parsing the message.

## Business Rules & Edge Cases

- **A slot is required until something is on file for it, then optional.** `Rule::requiredIf` is evaluated per request, so a first submission must carry both — an account cannot enter verification with half its paperwork — while a later one may correct a single document without re-uploading the other.
- **PDFs are accepted, and that is why the media layer grew a pass-through.** A business licence is commonly a PDF, which no image encoder can scale. It is stored once as uploaded under `original/` and every read of it resolves back there; see [media uploads](media-uploads.md#pass-through-formats). Accepted formats: `jpg`, `jpeg`, `png`, `webp`, `pdf`, at most 4 MB.
- **Documents never touch the public disk.** They are stored through `MediaPlacement::Document` under `restaurant/document/{variant}/{filename}` on the private disk, with random filenames, and come back only through an authenticated stream. A test asserts the public disk stays empty.
- **`role:admin` is not the whole authorization question on the admin route.** The middleware proves the *caller* is an admin; it says nothing about the bound id. All four roles share the `users` table, so `doc_image1` exists on a customer's row too and the slot map would happily resolve it. `UserPolicy::viewDocuments()` refuses any id that does not name a restaurant — a 403, not a 404, because the row exists and the caller simply may not read it that way.
- **The Policy has no owner branch, deliberately.** A restaurant reads its own documents through the self-scoped route, which takes no id and asks no Policy. Allowing the owner here would be a permission no route can exercise.
- **Replacing a document deletes the old file**, via `ImageUploadService::store(..., replacing:)` — after the new one is written, and across formats, so swapping a scan for a PDF leaves no orphaned variants.
- **Nothing that leaves the server carries a filename.** The admin email and the stored notification payload name the *kind* of file on record (`PDF document` / `Image`) and whether each slot is filled — never a path, a filename, or an attachment. The admin reads the file through the endpoint.
- **Every save notifies every admin**, first filing or correction alike, through `RestaurantDocumentUpdatedNotification` (mail + database) — a correction invalidates whatever the previous version was verified against. A submission that stored nothing sends nothing.
- **An unknown slot, an empty slot, or a row pointing at a missing file is a 404**, from `RestaurantDocumentService` (a `DomainException`), never an empty 200 body. An unknown restaurant id on the admin route is a 404 from route-model binding.
- **One service, flat under `app/Services/`**, matching `NewsletterService` and `RiderVehicleService` rather than the roadmap's `Services/Restaurant/` — a sub-namespace arrives when a domain has several classes.
- **The slot map is defined once**, in `RestaurantDocumentService::SLOTS`, and the Form Request, Resource and notification all build from it. A third document should be one entry there, not another block in four files.

## Tests

`tests/Feature/Restaurant/DocumentTest.php` (32) — reading with one, both and neither slot filled; the response never exposing a stored filename; filing both (201) and correcting after completion (200); a PDF accepted and reported as one; admins notified, and the payload naming the kind of file rather than the file; empty first submission rejected; one slot corrected alone; replacement removing the old file; disallowed format and oversize rejection; streaming own document; unknown and empty slots as 404; the self-scoped download serving nobody but the caller; the admin read of a named restaurant; the admin refused an id that names a customer; unknown id as 404; 403 for each wrong role on every route; and 401 on all of them.

Tests mint real tokens and send a `Bearer` header, and use one data-provider case per role rather than a loop: the sanctum guard memoizes the resolved user for the lifetime of a test method. Where a test needs another user's file to exist, it seeds the disk and the row directly rather than spending its one authenticated request uploading as them.
