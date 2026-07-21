<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Newsletter\SubscribeRequest;
use App\Http\Resources\NewsletterSubscriberResource;
use App\Http\Traits\ApiResponse;
use App\Services\NewsletterService;
use Illuminate\Http\JsonResponse;

/**
 * The public newsletter surface: sign up, confirm, opt out.
 *
 * All three are anonymous. `subscribe` is the only endpoint in the API an
 * unauthenticated caller can write through, and the two link endpoints are
 * reached by whoever holds the emailed token — which is the point, since a
 * subscriber has no account to log into.
 *
 * **`confirm` and `unsubscribe` are POST, not the roadmap's GET.** MealHub used
 * GET because a mail client opened those URLs directly; here the emailed link
 * lands on the SPA, which then calls the API, so the verb is free to be the
 * correct one for a state change. A GET that mutates is also confirmable by
 * anything that follows a URL — a crawler, a link preview, a prefetch.
 */
class NewsletterController extends Controller
{
    use ApiResponse;

    /**
     * The one message every signup gets back.
     *
     * A constant so the three paths that reach it — new address, already
     * pending, already confirmed — cannot drift into distinguishable wording
     * and leak list membership to an anonymous caller.
     */
    private const SIGNUP_MESSAGE = 'Almost there — check your inbox to confirm.';

    public function __construct(
        private readonly NewsletterService $newsletterService,
    ) {}

    /**
     * Take a signup. Throttled on the route; deliberately returns no data.
     */
    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $this->newsletterService->subscribe($request->validated()['email']);

        return $this->successResponse(null, self::SIGNUP_MESSAGE);
    }

    /**
     * Confirm a signup from its emailed token. Idempotent — a second call is a
     * 200, and does not move the original `confirmed_at`.
     */
    public function confirm(string $token): JsonResponse
    {
        return $this->successResponse(
            new NewsletterSubscriberResource($this->newsletterService->confirm($token)),
            'Subscription confirmed.',
        );
    }

    /**
     * Opt an address out. Also idempotent, and works whether or not the address
     * ever confirmed.
     */
    public function unsubscribe(string $token): JsonResponse
    {
        return $this->successResponse(
            new NewsletterSubscriberResource($this->newsletterService->unsubscribe($token)),
            'You have been unsubscribed.',
        );
    }
}
