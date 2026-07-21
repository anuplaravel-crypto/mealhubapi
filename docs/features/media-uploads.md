# Media & image uploads

The one place an uploaded image is written, replaced, or removed. Every upload becomes four files — the untouched original plus three scaled variants — under a fixed storage layout, on a disk chosen by whether the image is public marketing content or somebody's personal data.

**Status:** ✅ Implemented as [roadmap](../roadmap.md) Phase 4, an enabler with no surface of its own. Phase 5 ([profile pictures](profile.md)) is its first caller and brought the authenticated streaming controller with it; Phases 8 (rider vehicles), 9 (restaurant documents) and 10 (admin CMS write) still have to arrive.

## Endpoints

This feature owns none of its own. Uploads and reads belong to the domain that stores them:

| Method | Path | Feature |
| --- | --- | --- |
| POST | `/api/v1/profile/picture` | [Profile](profile.md) |
| GET | `/api/v1/media/profile-picture` | [Profile](profile.md) |

The public half is visible through [`GET /api/v1/home`](home-cms.md), whose Resources emit absolute URLs for images stored under this layout.

## Request / Response

- Business logic: `App\Services\Media\ImageUploadService`
- Storage layout: `App\Services\Media\MediaPlacement` (enum)
- Validated by: `App\Http\Requests\Concerns\ValidatesUploadedImage` — a trait for the Form Requests of the phases above, not a Form Request itself
- Read back by: `App\Http\Resources\Concerns\ResolvesImageUrl` (public images only)
- Data access: none — this tier touches the filesystem, not the database. The caller persists the returned filename on its own model through its own repository.

`ImageUploadService::store()` returns the **stored filename only** (a 40-character random name plus its extension). That string is what a model's `image` / `logo` / `avatar` column holds; the directory is never stored, because it is derivable from the placement and collection.

### Storage layout

| Placement | Disk | Path | Variants (longest edge) |
| --- | --- | --- | --- |
| `MediaPlacement::Cms` | `public` | `cms/{collection}/{variant}/{filename}` | small 400, medium 800, large 1600, original |
| `MediaPlacement::Personal` | `local` (private) | `{collection}/{variant}/{filename}` | small 150, medium 400, large 800, original |

`{collection}` for CMS imagery is the model's `IMAGE_COLLECTION` constant (`site`, `testimonials`, `sections`, `meal-categories`, `featured-restaurants`). For personal files it carries the owning role, e.g. `customer/profile` — the four roles share one `users` table, so the role has to be in the path for the private tree to be navigable.

**CMS images require `php artisan storage:link`.** Without the symlink the files are written but every URL 404s.

## Business Rules & Edge Cases

- **Public versus private is a storage decision, not a URL one.** CMS images are anonymous marketing assets, so they are linked directly as absolute URLs — a cross-origin SPA should not proxy every logo through PHP, which is what MealHub's `profile/image` route did for avatars. Personal files get the opposite treatment: the private disk, read back only through `Api\V1\MediaController`, which streams the caller their own file. A stored personal path is therefore not fetchable by URL alone.
- **`MediaPlacement` is the single source of truth for paths.** Both the writer (`ImageUploadService`) and the reader (`ResolvesImageUrl`) build their paths from `MediaPlacement::path()`, so they cannot drift apart — the Phase 3 trait used to restate the layout in a comment that only asked the future upload service to match it.
- **Replacement cleans up, in that order.** Pass the outgoing filename as `store(..., replacing: $old)` and its four variants are deleted *after* the new ones are safely written. A failure while encoding must not leave the record pointing at an image that no longer exists. Callers that skip the argument leak the old files — an unreferenced variant is unreachable and never reclaimed.
- **Transparency is preserved.** PNG and WebP sources are re-encoded to themselves; everything else becomes JPEG. Flattening a transparent PNG onto JPEG's opaque canvas turns the transparency black, which on a white navbar reads as a solid box where the logo should be. MealHub applied this rule to CMS images only and forced profile pictures to JPEG; it applies to both here.
- **The original is never scaled**, so larger variants can be regenerated later without asking the user to upload again. Variants use `scaleDown`, so an image smaller than a ceiling is left alone rather than upscaled into blur.
- **Filenames are random, never derived from the upload.** A stored name leaks nothing about its owner and a private file cannot be guessed by URL.
- **An unknown variant degrades to `medium`.** Variants arrive from query strings, so an unrecognised one must resolve to a real image rather than 404 on a path that was never written.
- **`pathFor()` verifies existence** and returns `null` when the file is missing, so `MediaController` answers 404 instead of streaming an empty body — a row pointing at a file that is no longer on disk reads the same as no file at all.
- **Validation is not the service's job.** `ImageUploadService` assumes an already-validated upload; size and format live in `ValidatesUploadedImage` so a bad file becomes a 422 with field-level errors rather than an exception from GD. Accepted: `jpg`, `jpeg`, `png`, `webp`, at most 2 MB. **SVG is deliberately excluded** — it is an XSS vector when served from a public disk, and the GD driver cannot rasterise it into the variants every other format gets.
- **Rendering uses GD, not Imagick** (`ImageManager::gd()`), matching MealHub. GD ships with the project's PHP; Imagick does not.

## Dependencies

`intervention/image` `^3.11` was added for this phase (matching MealHub's pin), per the roadmap's just-in-time dependency table.

## Tests

`tests/Feature/Media/ImageUploadServiceTest.php` — 25 tests, all against `Storage::fake()`: the four-file layout per placement, per-variant scaling and the un-upscaled small source, private files never reaching the public disk, format preservation across jpg/jpeg/png/webp, filename randomness, replacement removing every old variant while an unrelated image survives, delete and its null no-op, `pathFor` hit/fallback/miss, and the round-trip proving `ResolvesImageUrl` resolves exactly the path the service wrote.

Validation is covered in the same file through the trait's rules directly: oversize rejection, non-image rejection, SVG rejection, all three accepted formats, and required-versus-nullable.
