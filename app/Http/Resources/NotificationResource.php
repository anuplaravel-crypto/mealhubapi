<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * One stored in-app notification.
 *
 * Three shaping decisions:
 *
 * - **`type` is the semantic token from the payload, not the class name.**
 *   `DatabaseNotification::$type` holds `App\Notifications\RegistrationNotification`,
 *   an internal name of the kind the exception handler already works to keep
 *   out of responses, and one that would break every client the day a class
 *   is renamed. What ships is the `type` key each notification writes into its
 *   own `toArray()` — `customer_registration`, `account_status`.
 * - **`title` and `message` are lifted, and `data` still ships whole.** Every
 *   notification is guaranteed to render with those two, whatever kind it is;
 *   the kind-specific extras (`user`, `activated`) stay in `data` for clients
 *   that switch on `type`. MealHub's popup services lifted a different set of
 *   keys per role, which is exactly what one Resource replaces.
 * - **Timestamps are machine-readable.** MealHub sent `diffForHumans()`;
 *   "2 hours ago" is a rendering decision, and a localisation one, that
 *   belongs to the client — the same reasoning that keeps CSS classes and
 *   route names out of the CMS resources.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : [];

        return [
            'id' => $this->id,
            'type' => $data['type'] ?? null,
            'title' => $data['title'] ?? 'Notification',
            'message' => $data['message'] ?? '',
            'data' => $data,
            'read' => $this->read_at !== null,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
