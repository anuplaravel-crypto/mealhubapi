<?php

namespace App\Services\Cms;

use App\Repositories\Cms\BaseCmsRepository;
use App\Services\Media\ImageUploadService;
use App\Services\Media\MediaPlacement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * The shared write rules behind every reorderable CMS collection.
 *
 * The reference app carried six services with the same five methods — list,
 * create, update, toggle, delete — differing only in which model they touched,
 * which image collection they wrote to, and one or two placement quirks. That
 * is a set of parameters, not six classes, so the shape lives here and each
 * concrete service declares only what is genuinely its own.
 *
 * Two CMS resources deliberately do not extend this. {@see SiteSettingService}
 * is a singleton with nothing to list, create, delete or toggle, and the
 * home-section service (Phase 10's remaining batch) has no create or delete
 * either — a section row only means something alongside a client that renders
 * its key, so the seeder owns that set.
 *
 * @template TModel of Model
 */
abstract class BaseCmsService
{
    /**
     * @param  BaseCmsRepository<TModel>  $records
     */
    public function __construct(
        protected readonly BaseCmsRepository $records,
        protected readonly ImageUploadService $images,
    ) {}

    /**
     * The storage collection this resource's images live under — the model's
     * `IMAGE_COLLECTION` — or null for a collection that carries no imagery at
     * all, like home stats and nav links.
     */
    abstract protected function imageCollection(): ?string;

    /**
     * The column holding an uploaded filename. `image` for most, `avatar` on
     * testimonials, `logo` on site settings.
     */
    protected function imageField(): string
    {
        return 'image';
    }

    /**
     * The companion column holding an externally hosted link, or null when the
     * resource has no such fallback.
     */
    protected function imageUrlField(): ?string
    {
        return 'image_url';
    }

    /**
     * Every CMS image is public marketing imagery served straight off the
     * public disk — a cross-origin client must not proxy logos through PHP.
     */
    protected function placement(): MediaPlacement
    {
        return MediaPlacement::Cms;
    }

    /**
     * Hook for rules that normalise a payload before it is written — dropping
     * fields that mean nothing in the chosen placement, say. The default is a
     * pass-through.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data): array
    {
        return $data;
    }

    /**
     * The group a new row's `sort_order` should be counted within, read from
     * the incoming payload. Null for a table with one continuous order.
     *
     * @param  array<string, mixed>  $data
     */
    protected function groupFrom(array $data): int|string|null
    {
        return null;
    }

    /**
     * Every row in display order, for the admin list.
     *
     * @return Collection<int, TModel>
     */
    public function listAll(): Collection
    {
        return $this->records->allOrdered();
    }

    /**
     * Add a row.
     *
     * The model is refreshed before it is returned. Eloquent's `create()` hands
     * back the instance it built from the payload, which knows nothing about
     * columns the *database* defaulted — so a review created without an
     * explicit `is_published` came back with it as null rather than true, and a
     * client had no way to tell whether what it just created was live.
     *
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function create(array $data, ?UploadedFile $image = null): Model
    {
        $data = $this->prepare($data);
        $data['sort_order'] ??= $this->records->nextSortOrder($this->groupFrom($data));

        if ($image !== null) {
            $data = $this->applyImage($data, $image);
        }

        return $this->records->create($data)->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function update(int|string $id, array $data, ?UploadedFile $image = null): Model
    {
        $record = $this->records->findOrFail($id);
        $data = $this->prepare($data);

        if ($image !== null) {
            $data = $this->applyImage($data, $image, replacing: $record->{$this->imageField()});
        }

        return $this->records->update($record, $data);
    }

    /**
     * Flip whether a row appears on the public page.
     *
     * Toggling rather than accepting a target state keeps the endpoint honest
     * about what it does, and means the client cannot publish something by
     * replaying a stale request.
     *
     * @return TModel
     */
    public function togglePublished(int|string $id): Model
    {
        $record = $this->records->findOrFail($id);

        return $this->records->update($record, ['is_published' => ! $record->is_published]);
    }

    /**
     * Remove a row and any image it owns.
     *
     * The file goes first: a row deleted while its variants survive leaves four
     * files nothing can ever reach again, since the filename was the only
     * reference to them.
     */
    public function delete(int|string $id): void
    {
        $record = $this->records->findOrFail($id);

        if ($this->imageCollection() !== null) {
            $this->images->delete($this->placement(), $this->imageCollection(), $record->{$this->imageField()});
        }

        $this->records->delete($record);
    }

    /**
     * Store an uploaded image and clear the external link.
     *
     * The two columns are a preference, not alternatives — the Resource
     * resolves an upload ahead of a link — so leaving a stale URL behind would
     * keep a value that can never be shown and would silently reappear the day
     * the upload was removed.
     *
     * Replacement is passed to the service as an argument rather than being
     * followed by a delete here: cleanup ordering is the upload service's job,
     * and a failure mid-encode must not leave the row pointing at a file that
     * has already been deleted.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyImage(array $data, UploadedFile $image, ?string $replacing = null): array
    {
        if ($this->imageCollection() === null) {
            return $data;
        }

        $data[$this->imageField()] = $this->images->store(
            $this->placement(),
            $this->imageCollection(),
            $image,
            replacing: $replacing,
        );

        if ($this->imageUrlField() !== null) {
            $data[$this->imageUrlField()] = null;
        }

        return $data;
    }
}
