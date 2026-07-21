<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Traits\ApiResponse;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;

/**
 * A signed-in user's in-app notifications, one controller for all four roles.
 *
 * MealHub carried four identical copies of this — customer, admin, restaurant
 * and rider — each behind its own guard and Blade layout, for 24 routes doing
 * six distinct things. The role arrives on the token here, so there is one
 * copy and six routes; the payloads already differ by notification kind rather
 * than by who is reading them.
 *
 * The list endpoints are scoped to the caller by the repository. The four that
 * take an id are scoped by `NotificationPolicy`, applied here before the
 * service is reached — this is the first route in the codebase where an id
 * arrives from the URL, so it is the first that can be pointed at somebody
 * else's row.
 */
class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * The caller's full history, newest first, one page at a time.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse(
            NotificationResource::collection($this->notificationService->paginateFor($request->user())),
        );
    }

    /**
     * The bell payload — unread count plus the newest unread notifications.
     *
     * Separate from {@see self::index()} because a client polls this one: it
     * is a fixed, small response, and it carries the badge number even when
     * the user is nowhere near the notification list.
     */
    public function unread(Request $request): JsonResponse
    {
        $unread = $this->notificationService->unreadFor($request->user());

        return $this->successResponse([
            'count' => $unread['count'],
            'notifications' => NotificationResource::collection($unread['notifications']),
        ]);
    }

    /**
     * Mark one notification as read. Idempotent — a repeat is a 200, not an
     * error, and does not move the original `read_at`.
     */
    public function markAsRead(DatabaseNotification $notification): JsonResponse
    {
        Gate::authorize('update', $notification);

        return $this->successResponse(
            new NotificationResource($this->notificationService->markAsRead($notification)),
            'Notification marked as read.',
        );
    }

    /**
     * Clear the caller's unread badge in one call, reporting how many
     * notifications were still unread when it ran.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $marked = $this->notificationService->markAllAsRead($request->user());

        return $this->successResponse(['marked' => $marked], 'All notifications marked as read.');
    }

    /**
     * Flip one notification between read and unread — how a user re-flags
     * something they opened by accident.
     */
    public function toggleRead(DatabaseNotification $notification): JsonResponse
    {
        Gate::authorize('update', $notification);

        return $this->successResponse(
            new NotificationResource($this->notificationService->toggleRead($notification)),
        );
    }

    public function destroy(DatabaseNotification $notification): JsonResponse
    {
        Gate::authorize('delete', $notification);

        $this->notificationService->delete($notification);

        return $this->noContentResponse();
    }
}
