<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\Media\ImageUploadService;
use App\Services\Media\MediaPlacement;
use Illuminate\Http\UploadedFile;

/**
 * A signed-in user maintaining their own account details, for all four roles.
 *
 * MealHub carried four of these — `Customer`, `Admin`, `Restaurant` and
 * `RiderProfileService` — which a diff shows to be byte-identical apart from
 * the role directory their pictures land in. That is an argument, not four
 * classes, so they collapse here on the same reasoning that keeps `AuthService`
 * single and role-parameterized.
 *
 * Every method takes the already-authenticated `User` rather than an id: this
 * whole domain is self-scoped, so there is no id from the request that could
 * point somewhere else, and therefore no ownership check to forget.
 */
class ProfileService
{
    /**
     * The columns a user may change about themselves.
     *
     * `email` is absent because it is the account identifier and the anchor of
     * OTP verification — changing it would need its own re-verification flow.
     * `role`, `status` and `is_email_verified` are administrative: `status` is
     * the admin-approval gate that `UserManagementService` flips, and nothing self-served may
     * touch it. The Form Request already whitelists the payload; this list is
     * the second lock, so a field added to the request later cannot silently
     * become self-editable.
     *
     * @var list<string>
     */
    private const EDITABLE_FIELDS = [
        'firstName',
        'lastName',
        'mobile',
        'preferred_language',
        'address1',
        'address2',
        'zip_code',
        'country_id',
        'county_id',
        'city_id',
    ];

    public function __construct(
        private readonly UserRepository $users,
        private readonly ImageUploadService $images,
    ) {}

    /**
     * The caller's own profile, with its location rows attached.
     */
    public function show(User $user): User
    {
        return $this->users->withLocation($user);
    }

    /**
     * Apply a validated update to the caller's own profile and return the row
     * as it now stands, ready to re-render.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $this->users->update($user, array_intersect_key($data, array_flip(self::EDITABLE_FIELDS)));

        return $this->users->withLocation($user);
    }

    /**
     * Replace the caller's profile picture and return the updated user.
     *
     * The outgoing filename is handed to the upload service rather than deleted
     * here: it removes the old variants only once the new ones are written, so
     * a failure mid-encode cannot leave the row pointing at a deleted image.
     */
    public function updatePicture(User $user, UploadedFile $image): User
    {
        $filename = $this->images->store(
            MediaPlacement::Personal,
            $this->collectionFor($user),
            $image,
            replacing: $user->image,
        );

        $this->users->update($user, ['image' => $filename]);

        return $this->users->withLocation($user);
    }

    /**
     * Disk-relative path of the caller's own profile picture, for the streaming
     * controller to serve.
     *
     * Profile pictures live on the private disk, so this path is not fetchable
     * by URL — it is only ever reached through an authenticated request for the
     * caller's own file. A user with no picture, or a row pointing at a file
     * that is no longer on disk, is a 404 rather than an empty body.
     *
     * @throws DomainException when there is nothing to serve
     */
    public function picturePath(User $user, ?string $variant = null): string
    {
        $path = $this->images->pathFor(
            MediaPlacement::Personal,
            $this->collectionFor($user),
            $user->image,
            $variant,
        );

        if ($path === null) {
            throw new DomainException('Resource not found.', 404);
        }

        return $path;
    }

    /**
     * Storage collection for one user's personal images, e.g. `rider/profile`.
     *
     * The role is part of the path because all four roles share the `users`
     * table — without it the private tree is one flat pile of avatars.
     */
    private function collectionFor(User $user): string
    {
        return $user->role.'/'.User::IMAGE_COLLECTION;
    }
}
