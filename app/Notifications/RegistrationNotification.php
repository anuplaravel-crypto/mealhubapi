<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\FormatsUserDetails;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the admins that somebody signed up — emailed, and stored so it shows
 * in the admin's in-app list.
 *
 * One role-parameterized class rather than the reference app's three, on the
 * same argument as {@see OtpNotification}: the customer, restaurant and rider
 * versions differed only in the noun and in one line about what the admin has
 * to do next. The registering user is the *subject* here, never the notifiable
 * — this is addressed to every admin.
 *
 * The stored payload keys are `user` / `user_id` rather than the reference
 * app's per-role `customer` / `rider_id`, which existed because a different
 * popup script read each one. A client switches on `type` and reads one shape.
 */
class RegistrationNotification extends Notification
{
    use FormatsUserDetails, Queueable;

    public function __construct(private readonly User $user) {}

    /**
     * Delivery channels — email to the admin plus an in-app record.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * The email each admin receives.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');

        $message = (new MailMessage)
            ->subject("New {$this->roleLabel()} Registration — {$app}")
            ->greeting('Hello Admin,')
            ->line("A new {$this->user->role} has registered on {$app}{$this->awaitingClause()}.")
            ->line("**{$this->roleLabel()} details**")
            ->line('Name: '.$this->fullName($this->user))
            ->line('Mobile: '.($this->user->mobile ?: 'Not provided'))
            ->line('Email: '.$this->user->email)
            ->line('Address: '.$this->fullAddress($this->user));

        if ($this->user->role === 'restaurant') {
            $message->line('Once they have uploaded their business licence and photo identification you can verify the documents and activate the account.');
        }

        return $message->salutation("Regards,\n{$app} Team");
    }

    /**
     * The payload stored in the notifications table.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->user->role.'_registration',
            'title' => "New {$this->user->role} registration",
            'message' => $this->fullName($this->user)." registered as a {$this->user->role}{$this->awaitingClause()}.",
            'role' => $this->user->role,
            'user_id' => $this->user->id,
            'user' => $this->personalDetails($this->user),
        ];
    }

    private function roleLabel(): string
    {
        return ucfirst($this->user->role);
    }

    /**
     * Restaurants and riders cannot work until an admin approves them;
     * customers are usable the moment they verify their email. That gate is
     * the only thing the three ported classes actually said differently.
     */
    private function awaitingClause(): string
    {
        return in_array($this->user->role, ['restaurant', 'rider'], true)
            ? ' and is awaiting verification'
            : '';
    }
}
