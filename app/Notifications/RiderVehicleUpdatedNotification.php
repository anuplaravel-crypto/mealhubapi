<?php

namespace App\Notifications;

use App\Models\RiderVehicle;
use App\Models\User;
use App\Notifications\Concerns\FormatsUserDetails;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the admins that a rider submitted or changed their vehicle details —
 * emailed, and stored so it shows in the admin's in-app list.
 *
 * Flat under `app/Notifications/` rather than the reference app's
 * `Notifications/Rider/`, alongside the two account notices, and built on the
 * shared {@see FormatsUserDetails} block that `RegistrationNotification` uses —
 * which is exactly the reuse that trait was kept for.
 *
 * The rider is the *subject* here, never the notifiable: this is addressed to
 * every admin, because an unverified vehicle is a queue item rather than news
 * for the person who submitted it.
 *
 * **An edit notifies just as loudly as a first submission.** The verification an
 * admin already granted was granted against the previous details, so a rider who
 * swapped their plate after approval is back in the queue.
 */
class RiderVehicleUpdatedNotification extends Notification
{
    use FormatsUserDetails, Queueable;

    public function __construct(
        private readonly User $rider,
        private readonly RiderVehicle $vehicle,
        private readonly bool $isNew = false,
    ) {}

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
     *
     * No action button: the SPA's admin route for a pending rider is not
     * something this API knows, and `RegistrationNotification` — the other
     * admin-facing notice, raised at the other end of the same onboarding —
     * ends without one for the same reason. A link here and none there would be
     * the inconsistency, not the omission.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');

        return (new MailMessage)
            ->subject('Rider Vehicle Information '.ucfirst($this->verb())." — {$app}")
            ->greeting('Hello Admin,')
            ->line($this->fullName($this->rider).' has '.$this->verb()." their vehicle information on {$app}.")
            ->line('**Rider details**')
            ->line('Name: '.$this->fullName($this->rider))
            ->line('Mobile: '.($this->rider->mobile ?: 'Not provided'))
            ->line('Email: '.$this->rider->email)
            ->line('Address: '.$this->fullAddress($this->rider))
            ->line('**Vehicle details**')
            ->line('Type: '.ucfirst((string) $this->vehicle->vehicle_type))
            ->line('Registration No: '.($this->vehicle->registration_number ?: 'Not provided'))
            ->line('Brand: '.($this->vehicle->vehicle_brand ?: 'Not provided'))
            ->line('Model: '.($this->vehicle->vehicle_model ?: 'Not provided'))
            ->line('Colour: '.($this->vehicle->vehicle_color ?: 'Not provided'))
            ->line('Please review the vehicle documents to verify and activate this rider.')
            ->salutation("Regards,\n{$app} Team");
    }

    /**
     * The payload stored in the notifications table.
     *
     * The photo is named but not linked: it lives on the private disk, so there
     * is no URL to put here — an admin read path arrives with Phase 11's view of
     * a rider, which is where the file becomes reachable.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'rider_vehicle_updated',
            'title' => 'Rider vehicle '.$this->verb(),
            'message' => $this->fullName($this->rider).' '.$this->verb().' their vehicle information.',
            'role' => $this->rider->role,
            'user_id' => $this->rider->id,
            'user' => $this->personalDetails($this->rider),
            'vehicle' => [
                'id' => $this->vehicle->id,
                'vehicle_type' => $this->vehicle->vehicle_type,
                'registration_number' => $this->vehicle->registration_number,
                'vehicle_brand' => $this->vehicle->vehicle_brand,
                'vehicle_model' => $this->vehicle->vehicle_model,
                'vehicle_color' => $this->vehicle->vehicle_color,
                'image' => $this->vehicle->image,
                'is_active' => $this->vehicle->is_active,
            ],
        ];
    }

    /**
     * The one word separating a first submission from an edit, used in the
     * subject, the body and the stored title.
     */
    private function verb(): string
    {
        return $this->isNew ? 'submitted' : 'updated';
    }
}
