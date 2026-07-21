<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Media\MediaPlacement;
use App\Services\RestaurantDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * An admin reading a named restaurant's filed documents, so the account can be
 * verified.
 *
 * **The first read path in the codebase that names another user**, which is why
 * it is separated from the restaurant's own controller rather than being an
 * extra argument on it: this one takes an id from the URL, so it carries a
 * Policy, and keeping the two apart means the self-scoped action cannot quietly
 * acquire an id later without noticing what that implies.
 *
 * `role:admin` on the route is not the whole answer either — it proves the
 * caller is an admin, not that `{restaurant}` names a restaurant. A bound id
 * pointing at a customer or a rider is refused by `UserPolicy::viewDocuments()`.
 */
class RestaurantDocumentController extends Controller
{
    public function __construct(
        private readonly RestaurantDocumentService $documentService,
    ) {}

    /**
     * Stream one document belonging to the named restaurant.
     *
     * Defaults to the `large` variant rather than `medium`: an admin is reading
     * a licence number off the scan, not glancing at a thumbnail.
     */
    public function show(Request $request, User $restaurant, int $slot): StreamedResponse
    {
        Gate::authorize('viewDocuments', $restaurant);

        $variant = $request->query('variant');

        $path = $this->documentService->documentPath(
            $restaurant,
            $slot,
            is_string($variant) ? $variant : 'large',
        );

        return Storage::disk(MediaPlacement::Document->disk())->response($path);
    }
}
