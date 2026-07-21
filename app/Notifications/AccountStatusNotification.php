<?php

namespace App\Notifications;

use App\Events\UserStatusChanged;
use App\Listeners\SendAccountStatusNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a user an admin activated or deactivated their account.
 *
 * One role-parameterized class replacing the reference app's three, which
 * differed only in what the account can no longer do. Unlike
 * {@see RegistrationNotification} the role is **read off the notifiable**
 * rather than passed in: this is always addressed to the user whose status
 * changed, so an argument could only ever disagree with the recipient.
 *
 * **No dashboard link.** The activated email in the reference app ended with a
 * button pointing at its own Blade route. A cross-origin SPA has no such URL
 * until `FRONTEND_URL` lands in Phase 7 of docs/roadmap.md — the same reason
 * `OtpNotification` sends a code rather than a link. Add the action there, not
 * a placeholder here.
 *
 * Nothing dispatches this directly: the admin toggle fires
 * {@see UserStatusChanged} and
 * {@see SendAccountStatusNotification} sends it, so the toggle
 * never has to know that changing a flag also sends mail.
 */
class AccountStatusNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly bool $activated) {}

    /**
     * Delivery channels — email to the user plus an in-app record.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * The email sent to the user whose account changed state.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');
        $name = trim(($notifiable->firstName ?? '').' '.($notifiable->lastName ?? '')) ?: 'there';
        $account = $this->accountLabel($notifiable);

        if ($this->activated) {
            return (new MailMessage)
                ->subject("Your {$app} Account Is Active")
                ->greeting("Hello {$name},")
                ->line("Good news — your {$account} has been reviewed and activated.")
                ->line('You can now log in and '.$this->capability($notifiable).'.')
                ->salutation("Regards,\n{$app} Team");
        }

        return (new MailMessage)
            ->subject("Your {$app} Account Has Been Deactivated")
            ->greeting("Hello {$name},")
            ->line("Your {$account} has been deactivated by an administrator.")
            ->line('While deactivated you cannot '.$this->capability($notifiable).'. Please contact support if you believe this is a mistake.')
            ->salutation("Regards,\n{$app} Team");
    }

    /**
     * The payload stored in the notifications table.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'account_status',
            'title' => $this->activated ? 'Account activated' : 'Account deactivated',
            'message' => $this->activated
                ? 'Your account has been activated. You can now '.$this->capability($notifiable).'.'
                : 'Your account has been deactivated. You cannot '.$this->capability($notifiable).' until it is reactivated.',
            'activated' => $this->activated,
        ];
    }

    /**
     * `restaurant account`, `rider account`, or plain `account` for a customer
     * — the reference app's own phrasing per role.
     */
    private function accountLabel(object $notifiable): string
    {
        return $notifiable instanceof User && in_array($notifiable->role, ['restaurant', 'rider'], true)
            ? $notifiable->role.' account'
            : 'account';
    }

    /**
     * What the account is for — the one clause that differed between the three
     * ported per-role classes.
     */
    private function capability(object $notifiable): string
    {
        return match ($notifiable instanceof User ? $notifiable->role : null) {
            'restaurant' => 'receive orders',
            'rider' => 'accept delivery jobs',
            default => 'place meal orders',
        };
    }
}
