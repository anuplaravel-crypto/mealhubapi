<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\RiderVehicle;
use App\Models\User;
use App\Notifications\RiderVehicleUpdatedNotification;
use App\Repositories\RiderVehicleRepository;
use App\Repositories\UserRepository;
use App\Services\Media\ImageUploadService;
use App\Services\Media\MediaPlacement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

/**
 * The rider's single vehicle record — the one thing rider onboarding asks for
 * beyond the account itself.
 *
 * Flat under `app/Services/` rather than the roadmap's `Services/Rider/`, on the
 * same reasoning that kept `NewsletterService` flat: a sub-namespace arrives
 * when a domain has several classes, and this domain has one.
 *
 * Every method takes the already-authenticated rider rather than an id. A rider
 * has exactly one vehicle and reaches it through themselves, so — as with
 * `ProfileService` — there is no id from a request that could point at another
 * rider's row, and therefore no ownership check to forget.
 */
class RiderVehicleService
{
    public function __construct(
        private readonly RiderVehicleRepository $vehicles,
        private readonly UserRepository $users,
        private readonly ImageUploadService $images,
    ) {}

    /**
     * The rider's own vehicle.
     *
     * A rider who has not registered one yet is a 404 rather than a null body:
     * the record genuinely does not exist, and the client's next move is the
     * onboarding form either way.
     *
     * @throws DomainException 404 when nothing has been submitted yet
     */
    public function show(User $rider): RiderVehicle
    {
        $vehicle = $this->vehicles->forRider($rider);

        if ($vehicle === null) {
            throw new DomainException('You have not registered a vehicle yet.', 404);
        }

        return $vehicle;
    }

    /**
     * Create or update the rider's vehicle, and tell the admins either way.
     *
     * Every submit — first-time or an edit — notifies every admin, because an
     * edit invalidates the verification the previous version was approved
     * under: a rider could otherwise be approved on one plate and then quietly
     * swap in another.
     *
     * @param  array<string, mixed>  $data
     * @return array{vehicle: RiderVehicle, is_new: bool}
     */
    public function save(User $rider, array $data, ?UploadedFile $image = null): array
    {
        $existing = $this->vehicles->forRider($rider);

        if ($image !== null) {
            $data['image'] = $this->images->store(
                MediaPlacement::Personal,
                $this->collectionFor($rider),
                $image,
                replacing: $existing?->image,
            );
        }

        $vehicle = $this->vehicles->updateOrCreateForRider($rider, [
            ...$data,
            'is_active' => $this->activeFlagFor($rider),
        ]);

        Notification::send(
            $this->users->admins(),
            new RiderVehicleUpdatedNotification($rider, $vehicle, $existing === null),
        );

        return ['vehicle' => $vehicle, 'is_new' => $existing === null];
    }

    /**
     * Disk-relative path of the rider's own vehicle photo, for the streaming
     * controller to serve.
     *
     * The private counterpart to a public image URL, exactly as
     * {@see ProfileService::picturePath()} is: the file sits on the private disk
     * under an unguessable name and comes back only through an authenticated
     * request from its owner. No photo, or a row pointing at a file no longer on
     * disk, is a 404 rather than an empty body.
     *
     * @throws DomainException when there is nothing to serve
     */
    public function imagePath(User $rider, ?string $variant = null): string
    {
        $path = $this->images->pathFor(
            MediaPlacement::Personal,
            $this->collectionFor($rider),
            $this->vehicles->forRider($rider)?->image,
            $variant,
        );

        if ($path === null) {
            throw new DomainException('Resource not found.', 404);
        }

        return $path;
    }

    /**
     * `is_active` tracks the rider's `users.status` and is never the rider's to
     * set — an admin flips it by approving the account, which reaches the
     * vehicle rows through `SyncRiderVehicleStatus` rather than through here.
     *
     * Deriving it here rather than leaving the column's `default(true)` to stand
     * fixes a real inconsistency in the reference app: a rider registers with
     * `status = false`, so a vehicle inserted with `is_active = true` claimed to
     * be live for an account that was not. Re-deriving it on every save also
     * means an edit cannot resurrect the flag on a deactivated rider.
     */
    private function activeFlagFor(User $rider): bool
    {
        return (bool) $rider->status;
    }

    /**
     * Storage collection for a rider's vehicle photos — `rider/vehicle`.
     *
     * The role prefix is constant here, unlike `ProfileService`'s, because only
     * riders own vehicles. It is still read from the row so the private tree
     * keeps one shape: `{role}/{collection}/{variant}/{filename}`.
     */
    private function collectionFor(User $rider): string
    {
        return $rider->role.'/'.RiderVehicle::IMAGE_COLLECTION;
    }
}
