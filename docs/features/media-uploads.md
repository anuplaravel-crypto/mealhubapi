# Media & image uploads

The one place an uploaded image is written, replaced, or removed. Every upload becomes four files — the untouched original plus three scaled variants — under a fixed storage layout, on a disk chosen by whether the image is public marketing content or somebody's personal data.

**Status:** ✅ Implemented as [roadmap](../roadmap.md) Phase 4, an enabler with no surface of its own. Phase 5 ([profile pictures](profile.md)) is its first caller and brought the authenticated streaming controller with it; Phase 8 ([rider vehicles](rider-onboarding.md)) reused it unchanged, and Phase 9 ([restaurant documents](restaurant-documents.md)) added the `Document` placement and the pass-through path a PDF needs. Phase 10 (admin CMS write) still has to arrive.

## Endpoints

This feature owns none of its own. Uploads and reads belong to the domain that stores them:

| Method | Path | Feature |
| --- | --- | --- |
| POST | `/api/v1/profile/picture` | [Profile](profile.md) |
| GET | `/api/v1/media/profile-picture` | [Profile](profile.md) |
| POST | `/api/v1/rider/vehicle` | [Rider onboarding](rider-onboarding.md) |
| GET | `/api/v1/rider/vehicle/image` | [Rider onboarding](rider-onboarding.md) |
| POST | `/api/v1/restaurant/documents` | [Restaurant documents](restaurant-documents.md) |
| GET | `/api/v1/restaurant/documents/{slot}` | [Restaurant documents](restaurant-documents.md) |
| GET | `/api/v1/admin/restaurants/{restaurant}/documents/{slot}` | [Restaurant documents](restaurant-documents.md) |

The public half is visible through [`GET /api/v1/home`](home-cms.md), whose Resources emit absolute URLs for images stored under this layout.

## Request / Response

- Business logic: `App\Services\Media\ImageUploadService`
- Storage layout: `App\Services\Media\MediaPlacement` (enum)
- Validated by: `App\Http\Requests\Concerns\ValidatesUploadedImage` and `...\ValidatesUploadedDocument` — traits for the Form Requests of the phases above, not Form Requests themselves. One per class of file: display imagery, and identity paperwork.
- Read back by: `App\Http\Resources\Concerns\ResolvesImageUrl` (public images only)
- Data access: none — this tier touches the filesystem, not the database. The caller persists the returned filename on its own model through its own repository.

`ImageUploadService::store()` returns the **stored filename only** (a 40-character random name plus its extension). That string is what a model's `image` / `logo` / `avatar` column holds; the directory is never stored, because it is derivable from the placement and collection.

### Storage layout

| Placement | Disk | Path | Variants (longest edge) |
| --- | --- | --- | --- |
| `MediaPlacement::Cms` | `public` | `cms/{collection}/{variant}/{filename}` | small 400, medium 800, large 1600, original |
| `MediaPlacement::Personal` | `local` (private) | `{collection}/{variant}/{filename}` | small 150, medium 400, large 800, original |
| `MediaPlacement::Document` | `local` (private) | `{collection}/{variant}/{filename}` | small 300, medium 800, large 1600, original |

`{collection}` for CMS imagery is the model's `IMAGE_COLLECTION` constant (`site`, `testimonials`, `sections`, `meal-categories`, `featured-restaurants`). For private files it carries the owning role, e.g. `customer/profile`, `rider/vehicle`, `restaurant/document` — the four roles share one `users` table, so the role has to be in the path for the private tree to be navigable.

`Document` is separate from `Personal` for two reasons, both real: an admin has to *read* a licence number off a scan, so its ceilings match the CMS ones rather than an avatar's; and it is the only placement that accepts a file this service cannot rasterise.

### Pass-through formats

A PDF has no image encoder to go through, and a business licence is commonly filed as one. Rather than reject the format, `MediaPlacement::PASSTHROUGH_EXTENSIONS` marks it as stored **exactly as uploaded**: one file under `original/`, no scaled variants. `MediaPlacement::variantFor()` then resolves *every* read of it — `?variant=small`, `large`, or nothing at all — back to that one directory, so a caller asking for a size gets the document instead of a 404 on a path that could never have existed.

The rule is keyed on the stored file's extension, not on the placement, because a document collection legitimately holds a mix of scanned images and PDFs. `ImageUploadService` keeps its name: its job is unchanged — take an upload and store it the way its placement says.

**CMS images require `php artisan storage:link`.** Without the symlink the files are written but every URL 404s.

## Business Rules & Edge Cases

- **Public versus private is a storage decision, not a URL one.** CMS images are anonymous marketing assets, so they are linked directly as absolute URLs — a cross-origin SPA should not proxy every logo through PHP, which is what MealHub's `profile/image` route did for avatars. Private files get the opposite treatment: the private disk, read back only through an authenticated streaming action. A stored private path is therefore not fetchable by URL alone.
- **A private read lives on the controller owning that domain**, not on one central media controller: `MediaController` for avatars, `Rider\VehicleController` for vehicle photos, `Restaurant\DocumentController` and `Admin\RestaurantDocumentController` for paperwork. Each file's authorization question belongs next to the rules that produced it — an avatar is always the caller's own, a licence may also be read by an admin.
- **`MediaPlacement` is the single source of truth for paths.** Both the writer (`ImageUploadService`) and the reader (`ResolvesImageUrl`) build their paths from `MediaPlacement::path()`, so they cannot drift apart — the Phase 3 trait used to restate the layout in a comment that only asked the future upload service to match it.
- **Replacement cleans up, in that order.** Pass the outgoing filename as `store(..., replacing: $old)` and its four variants are deleted *after* the new ones are safely written. A failure while encoding must not leave the record pointing at an image that no longer exists. Callers that skip the argument leak the old files — an unreferenced variant is unreachable and never reclaimed.
- **Transparency is preserved.** PNG and WebP sources are re-encoded to themselves; everything else becomes JPEG. Flattening a transparent PNG onto JPEG's opaque canvas turns the transparency black, which on a white navbar reads as a solid box where the logo should be. MealHub applied this rule to CMS images only and forced profile pictures to JPEG; it applies to both here.
- **The original is never scaled**, so larger variants can be regenerated later without asking the user to upload again. Variants use `scaleDown`, so an image smaller than a ceiling is left alone rather than upscaled into blur.
- **Filenames are random, never derived from the upload.** A stored name leaks nothing about its owner and a private file cannot be guessed by URL.
- **An unknown variant degrades to `medium`.** Variants arrive from query strings, so an unrecognised one must resolve to a real image rather than 404 on a path that was never written.
- **`pathFor()` verifies existence** and returns `null` when the file is missing, so `MediaController` answers 404 instead of streaming an empty body — a row pointing at a file that is no longer on disk reads the same as no file at all.
- **Validation is not the service's job.** `ImageUploadService` assumes an already-validated upload; size and format live in the request traits so a bad file becomes a 422 with field-level errors rather than an exception from GD. Images (`ValidatesUploadedImage`): `jpg`, `jpeg`, `png`, `webp`, at most 2 MB. Documents (`ValidatesUploadedDocument`): the same plus `pdf`, at most 4 MB, since a legible multi-page scan is simply bigger than an avatar. **SVG is excluded from both** — it is an XSS vector when served from a public disk, and the GD driver cannot rasterise it into the variants every other format gets.
- **Two traits, not one, and not rules restated in a Form Request.** A document is a different class of file from display imagery, with different answers on format and size; keeping one trait per class means nothing is duplicated and raising a ceiling is still one edit. What stays forbidden is a Form Request writing `mimes:`/`max:` itself.
- **Rendering uses GD, not Imagick** (`ImageManager::gd()`), matching MealHub. GD ships with the project's PHP; Imagick does not.

## Dependencies

`intervention/image` `^3.11` was added for this phase (matching MealHub's pin), per the roadmap's just-in-time dependency table.

## Tests

`tests/Feature/Media/ImageUploadServiceTest.php` — 33 tests, all against `Storage::fake()`: the four-file layout per placement, per-variant scaling and the un-upscaled small source, private files never reaching the public disk, format preservation across jpg/jpeg/png/webp, filename randomness, replacement removing every old variant while an unrelated image survives, delete and its null no-op, `pathFor` hit/fallback/miss, and the round-trip proving `ResolvesImageUrl` resolves exactly the path the service wrote.

The pass-through path is pinned there too: a PDF written once with no variants, every requested variant of it resolving to the original, replacement across formats (PDF→PDF and image→PDF) leaving nothing behind, and `isPassthrough()` being case-insensitive and null-safe.

Validation is covered in the same file through both traits' rules directly: oversize rejection, non-image rejection, SVG rejection, every accepted format, required-versus-nullable, and the one difference that matters — a PDF that the image rules reject and the document rules accept.
