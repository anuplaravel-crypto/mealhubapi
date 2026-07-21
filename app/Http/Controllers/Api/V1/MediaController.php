<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Media\MediaPlacement;
use App\Services\ProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The read path for personal files, deferred out of Phase 4 until something
 * actually stored one.
 *
 * Public CMS imagery is linked straight off the public disk — a cross-origin
 * SPA should not proxy every logo through PHP. Personal files get the opposite
 * treatment: they sit on the private disk with unguessable names, and this is
 * the only way back out.
 *
 * There is no id in the path. The file served is the token holder's own, which
 * is why this action needs no Policy; when Phase 9 adds restaurant documents
 * and an admin read path, *that* route takes an id and must bring a Policy with
 * it.
 */
class MediaController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
    ) {}

    /**
     * Stream the authenticated user's own profile picture.
     *
     * `variant` comes straight off the query string and is not validated: an
     * unrecognised size degrades to `medium` inside `MediaPlacement`, so there
     * is no input here that can fail. A user with no picture is a 404 raised by
     * the service.
     */
    public function show(Request $request): StreamedResponse
    {
        $variant = $request->query('variant');

        $path = $this->profileService->picturePath(
            $request->user(),
            is_string($variant) ? $variant : null,
        );

        return Storage::disk(MediaPlacement::Personal->disk())->response($path);
    }
}
