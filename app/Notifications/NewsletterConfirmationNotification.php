<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The double opt-in confirmation email.
 *
 * Mail only, and deliberately **not** stored in the `notifications` table: that
 * table is the in-app bell feed for authenticated users, and a newsletter
 * signup has no account to show it to. It is sent to the `NewsletterSubscriber`
 * row itself, which is `Notifiable` for exactly this reason.
 *
 * Flat under `app/Notifications/` alongside `OtpNotification` and the two
 * account notices, not MealHub's `Notifications/Newsletter/` sub-namespace.
 *
 * **The links point at the React SPA, not at this API.** This is the first
 * outbound link in the codebase, and the reason `FRONTEND_URL` exists — a
 * recipient who clicks an API address gets raw JSON and no way to act on it.
 * The SPA renders a page for each and calls the API from there. Every earlier
 * phase sent a code instead of a link precisely because there was no address to
 * send anyone to yet; this is the one flow where a code cannot work, since the
 * recipient may have no account and no screen open.
 */
class NewsletterConfirmationNotification extends Notification
{
    use Queueable;

    /**
     * @param  string  $token  The subscriber's persistent token — it serves the
     *                         unsubscribe link too, and is never cleared.
     */
    public function __construct(private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');

        return (new MailMessage)
            ->subject("Confirm your {$app} newsletter subscription")
            ->line("Someone — hopefully you — signed this address up for the {$app} newsletter.")
            ->line('Confirm below and we will send you fresh restaurants, offers and recipes. Until you do, we will not mail you anything else.')
            ->action('Confirm subscription', $this->frontendUrl('confirm'))
            // Offered without confirming first: the address may have been typed
            // in by somebody else, and that person deserves a one-click way out
            // that does not require opting in first to opt back out.
            ->line('Did not sign up? You can ignore this email, or opt this address out permanently here: '.$this->frontendUrl('unsubscribe'));
    }

    /**
     * A newsletter link on the SPA, carrying this subscriber's token.
     *
     * Falls back to `app.url` when `FRONTEND_URL` is unset so a same-origin
     * deployment still produces an absolute link rather than a relative
     * fragment no mail client can follow.
     */
    private function frontendUrl(string $action): string
    {
        $base = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');

        return "{$base}/newsletter/{$action}/{$this->token}";
    }
}
