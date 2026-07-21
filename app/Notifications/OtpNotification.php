<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The 6-digit code emailed for registration verification and password reset.
 *
 * One role-parameterized class rather than four: the reference app shipped a
 * separate VerifyAccountNotification per role, but the only real difference
 * between them was a single "what happens after you verify" line, which is
 * the $role argument here.
 *
 * Note this carries a **code, not a link**. The reference app's activation
 * emails linked to its own Blade routes; a cross-origin SPA has no such URL
 * to offer until FRONTEND_URL lands (Phase 7 of docs/roadmap.md), and this
 * API verifies by OTP anyway.
 */
class OtpNotification extends Notification
{
    use Queueable;

    /**
     * @param  string  $purpose  `registration` or `password_reset`
     * @param  string|null  $role  tailors the registration copy; ignored for password reset
     */
    public function __construct(
        public string $otp,
        public string $purpose,
        public ?string $role = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        if ($this->purpose === 'password_reset') {
            return $this->baseMessage($notifiable, 'Your MealHub password reset code')
                ->line('If you did not request this, you can safely ignore this email — your password has not changed.');
        }

        $message = $this->baseMessage($notifiable, $this->registrationSubject())
            ->line($this->nextStepLine());

        return $message->line('If you did not create this account, no further action is required.');
    }

    /**
     * The greeting, code, and expiry — identical for every purpose and role.
     */
    private function baseMessage(object $notifiable, string $subject): MailMessage
    {
        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello '.$notifiable->firstName.',')
            ->line('Your verification code is: '.$this->otp)
            ->line('This code will expire in 10 minutes.');
    }

    private function registrationSubject(): string
    {
        return match ($this->role) {
            'restaurant' => 'Verify your MealHub restaurant account',
            'rider' => 'Verify your MealHub rider account',
            default => 'Verify your MealHub email address',
        };
    }

    /**
     * What the account can do once verified — the one line that differed
     * between the reference app's three per-role notification classes.
     */
    private function nextStepLine(): string
    {
        return match ($this->role) {
            'restaurant' => 'After verifying, you can log in and upload your business licence and photo identification so an admin can approve your restaurant.',
            'rider' => 'After verifying, you can log in and add your vehicle details so an admin can approve you for deliveries.',
            default => 'After verifying, you can log in and start ordering freshly prepared meals near you.',
        };
    }
}
