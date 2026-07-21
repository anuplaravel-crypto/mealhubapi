<?php

namespace App\Http\Controllers\Api\V1\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\SaveDocumentRequest;
use App\Http\Resources\RestaurantDocumentResource;
use App\Http\Traits\ApiResponse;
use App\Services\Media\MediaPlacement;
use App\Services\RestaurantDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A restaurant's own identity paperwork: what is on file, filing it, and reading
 * one back.
 *
 * Role-gated to restaurants, and self-scoped — every action here works on
 * `$request->user()` and none takes a user id, so no Policy is involved. The
 * admin's read of a *named* restaurant is a separate controller precisely
 * because it does take an id, and therefore does need one.
 *
 * The download streams from here rather than from `Api/V1/MediaController` for
 * the same reason the rider's vehicle photo does: a private read belongs with
 * the domain whose rules produced the file.
 */
class DocumentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RestaurantDocumentService $documentService,
    ) {}

    /**
     * What the restaurant has filed so far.
     *
     * Answers for a restaurant that has filed nothing too — an empty pair of
     * slots is the honest state of a new account, not a 404, and it is exactly
     * what the onboarding screen renders from.
     */
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(new RestaurantDocumentResource($request->user()));
    }

    /**
     * File one or both documents.
     *
     * 201 while the paperwork was still incomplete, 200 once it was already
     * complete and this is a correction — the same split as the rider vehicle,
     * so a client can tell "entering verification" from "back for re-checking"
     * without parsing the message.
     */
    public function save(SaveDocumentRequest $request): JsonResponse
    {
        $result = $this->documentService->save($request->user(), [
            'doc_image1' => $request->file('doc_image1'),
            'doc_image2' => $request->file('doc_image2'),
        ]);

        return $this->successResponse(
            new RestaurantDocumentResource($result['restaurant']),
            $result['is_new']
                ? 'Documents uploaded. An admin will verify them and activate your account.'
                : 'Documents updated. They will be re-verified by an admin.',
            $result['is_new'] ? 201 : 200,
        );
    }

    /**
     * Stream one of the restaurant's own documents.
     *
     * `variant` is unvalidated for the same reason it is everywhere else: an
     * unrecognised size degrades to `medium` inside `MediaPlacement`, and a PDF
     * ignores it entirely. An unknown slot, an empty slot, or a row pointing at
     * a missing file is a 404 from the service.
     */
    public function download(Request $request, int $slot): StreamedResponse
    {
        $variant = $request->query('variant');

        $path = $this->documentService->documentPath(
            $request->user(),
            $slot,
            is_string($variant) ? $variant : null,
        );

        return Storage::disk(MediaPlacement::Document->disk())->response($path);
    }
}
