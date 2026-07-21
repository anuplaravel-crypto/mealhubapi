<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\User;
use App\Notifications\RestaurantDocumentUpdatedNotification;
use App\Repositories\UserRepository;
use App\Services\Media\ImageUploadService;
use App\Services\Media\MediaPlacement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

/**
 * The two identity documents every restaurant files before an admin will
 * activate the account: a business licence and a photo ID.
 *
 * Flat under `app/Services/` rather than the roadmap's `Services/Restaurant/`,
 * on the same reasoning that kept `NewsletterService` and
 * `RiderVehicleService` flat — a sub-namespace arrives when a domain has
 * several classes.
 *
 * Unlike every self-scoped service before it, one method here takes a `User`
 * that is *not* the caller: an admin reads a named restaurant's paperwork. That
 * read is authorized by `UserPolicy::viewDocuments()` before it arrives, and
 * this class still refuses to serve a user who is not a restaurant at all.
 */
class RestaurantDocumentService
{
    /**
     * The two slots, each mapping the URL's number onto its `users` column and
     * the names the client and the admin email use for it.
     *
     * A slot number rather than a column name in the URL: `doc_image1` is a
     * schema detail, and a client should not have to know it to fetch a licence.
     *
     * @var array<int, array{column: string, key: string, label: string}>
     */
    public const SLOTS = [
        1 => ['column' => 'doc_image1', 'key' => 'business_licence', 'label' => 'Business licence'],
        2 => ['column' => 'doc_image2', 'key' => 'photo_identification', 'label' => 'Photo identification'],
    ];

    public function __construct(
        private readonly UserRepository $users,
        private readonly ImageUploadService $documents,
    ) {}

    /**
     * Store whichever documents were uploaded and alert every admin so the new
     * paperwork can be (re-)verified.
     *
     * Both slots are optional on their own — the restaurant may resubmit one to
     * correct it without re-uploading the other — but the Form Request requires
     * a slot that is not yet on file, so an empty first submission cannot pass.
     *
     * `is_new` is "the file was incomplete before this", which is what decides
     * whether the account is entering verification or re-entering it.
     *
     * @param  array<string, UploadedFile|null>  $files  keyed by column
     * @return array{restaurant: User, is_new: bool}
     */
    public function save(User $restaurant, array $files): array
    {
        $isNew = ! $this->isComplete($restaurant);
        $stored = [];

        foreach (self::SLOTS as $slot) {
            $file = $files[$slot['column']] ?? null;

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $stored[$slot['column']] = $this->documents->store(
                MediaPlacement::Document,
                $this->collectionFor($restaurant),
                $file,
                replacing: $restaurant->{$slot['column']},
            );
        }

        if ($stored !== []) {
            $this->users->update($restaurant, $stored);

            Notification::send(
                $this->users->admins(),
                new RestaurantDocumentUpdatedNotification($restaurant, $isNew),
            );
        }

        return ['restaurant' => $restaurant, 'is_new' => $isNew];
    }

    /**
     * Whether both documents are on file — the condition an admin's approval
     * queue actually waits on.
     */
    public function isComplete(User $restaurant): bool
    {
        foreach (self::SLOTS as $slot) {
            if (blank($restaurant->{$slot['column']})) {
                return false;
            }
        }

        return true;
    }

    /**
     * Disk-relative path of one stored document, for a streaming controller.
     *
     * The restaurant reaches this for itself and an admin reaches it for a named
     * restaurant; authorization happens before the call, so what is left here is
     * the domain's own guard — a user who is not a restaurant has no document
     * slots, and an unknown slot number names nothing. Both are 404s, as is a
     * slot that was never filled or whose file has gone missing.
     *
     * @throws DomainException when there is nothing to serve
     */
    public function documentPath(User $restaurant, int $slot, ?string $variant = null): string
    {
        $column = self::SLOTS[$slot]['column'] ?? null;

        if ($column === null || $restaurant->role !== 'restaurant') {
            throw new DomainException('Resource not found.', 404);
        }

        $path = $this->documents->pathFor(
            MediaPlacement::Document,
            $this->collectionFor($restaurant),
            $restaurant->{$column},
            $variant,
        );

        if ($path === null) {
            throw new DomainException('Resource not found.', 404);
        }

        return $path;
    }

    /**
     * Storage collection for one restaurant's paperwork — `restaurant/document`.
     *
     * The role prefix is constant, like `RiderVehicleService`'s, but is still
     * read from the row so the private tree keeps one shape:
     * `{role}/{collection}/{variant}/{filename}`.
     */
    private function collectionFor(User $restaurant): string
    {
        return $restaurant->role.'/'.User::DOCUMENT_COLLECTION;
    }
}
