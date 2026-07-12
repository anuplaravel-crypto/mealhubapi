<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $otp,
        public string $purpose,
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
        $subject = $this->purpose === 'password_reset'
            ? 'Your MealHub password reset code'
            : 'Verify your MealHub email address';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello '.$notifiable->firstName.',')
            ->line('Your verification code is: '.$this->otp)
            ->line('This code will expire in 10 minutes.')
            ->line('If you did not request this, you can safely ignore this email.');
    }
}
