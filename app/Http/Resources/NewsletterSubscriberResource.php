<?php

namespace App\Http\Resources;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One newsletter signup.
 *
 * Serves both the public confirm/unsubscribe responses and the admin list —
 * one Resource, because they want the same fields. The public caller already
 * holds the token from the mailbox the address belongs to, so echoing that
 * address back discloses nothing it did not arrive with.
 *
 * **`token` is never exposed.** It is a bearer credential: anyone holding it
 * can confirm or unsubscribe that address without proving anything else. It
 * belongs in the email it was mailed in and nowhere else — not in the admin
 * list either, where it would be one screenshot away from leaking.
 *
 * `status` and `is_mailable` are the model's derived attributes rather than
 * columns, so a client never has to reimplement "confirmed but not since
 * unsubscribed" from two timestamps and get it half right.
 *
 * @mixin NewsletterSubscriber
 */
class NewsletterSubscriberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'status' => $this->status,
            'is_mailable' => $this->is_mailable,
            'confirmed_at' => $this->confirmed_at,
            'unsubscribed_at' => $this->unsubscribed_at,
            'created_at' => $this->created_at,
        ];
    }
}
