<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsletterSubscriberResource;
use App\Http\Traits\ApiResponse;
use App\Models\NewsletterSubscriber;
use App\Services\NewsletterService;
use Illuminate\Http\JsonResponse;

/**
 * The admin view of the newsletter list. **The first controller under
 * `Api/V1/Admin/`** — Phases 10 and 11 fill the namespace out.
 *
 * Read and delete only, on purpose. There is no "add subscriber" action: an
 * admin typing in somebody else's address is precisely the consent problem
 * double opt-in exists to prevent, and an admin-created row would either bypass
 * confirmation or send an unsolicited confirmation mail. Signups come from the
 * person who owns the address, or not at all.
 *
 * `destroy` takes an id from the URL and still carries no Policy, which is the
 * documented exception rather than an oversight: a subscriber has no owner to
 * check against, so `role:admin` is the whole authorization question. Phase
 * 10's CMS records are the same shape. Anything with an *owner* needs a Policy.
 */
class NewsletterController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly NewsletterService $newsletterService,
    ) {}

    /**
     * The subscriber list, newest signup first, one page at a time.
     */
    public function index(): JsonResponse
    {
        return $this->paginatedResponse(
            NewsletterSubscriberResource::collection($this->newsletterService->paginate()),
        );
    }

    /**
     * Erase a subscriber outright, for an erasure request.
     *
     * Distinct from unsubscribing: that is the subscriber's own action and
     * keeps the row so the opt-out is remembered. This forgets the address
     * entirely, so a later signup for it starts clean.
     */
    public function destroy(NewsletterSubscriber $subscriber): JsonResponse
    {
        $this->newsletterService->delete($subscriber);

        return $this->noContentResponse();
    }
}
