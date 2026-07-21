<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\FormatsUserDetails;
use App\Services\Media\MediaPlacement;
use App\Services\RestaurantDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the admins that a restaurant filed or corrected its identity documents —
 * emailed, and stored so it shows in the admin's in-app list.
 *
 * Flat under `app/Notifications/`, on the shared {@see FormatsUserDetails}
 * block, and shaped like {@see RiderVehicleUpdatedNotification}: the same
 * moment in the other role's onboarding deserves the same payload keys, so a
 * client switching on `type` reads one shape either way.
 *
 * The restaurant is the *subject*, never the notifiable — unverified paperwork
 * is a queue item for the admins, not news for the person who uploaded it.
 */
class RestaurantDocumentUpdatedNotification extends Notification
{
    use FormatsUserDetails, Queueable;

    public function __construct(
        private readonly User $restaurant,
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
     * No action button and no attachment: the SPA's route for a pending
     * restaurant is not something this API knows, and a licence must not leave
     * the private disk inside an email. The admin reads it through the
     * authenticated endpoint.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');

        $message = (new MailMessage)
            ->subject('Restaurant Documents '.ucfirst($this->verb())." — {$app}")
            ->greeting('Hello Admin,')
            ->line('A restaurant has '.$this->verb().' its identity documents and is awaiting verification.')
            ->line('**Restaurant details**')
            ->line('Name: '.$this->fullName($this->restaurant))
            ->line('Mobile: '.($this->restaurant->mobile ?: 'Not provided'))
            ->line('Email: '.$this->restaurant->email)
            ->line('Address: '.$this->fullAddress($this->restaurant))
            ->line('**Document details**');

        foreach (RestaurantDocumentService::SLOTS as $slot) {
            $message->line($slot['label'].': '.$this->describe($this->restaurant->{$slot['column']}));
        }

        return $message
            ->line('Please review the documents and activate the account if they are valid.')
            ->salutation("Regards,\n{$app} Team");
    }

    /**
     * The payload stored in the notifications table.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $documents = [];

        foreach (RestaurantDocumentService::SLOTS as $number => $slot) {
            $documents[$slot['key']] = [
                'slot' => $number,
                'label' => $slot['label'],
                'on_file' => filled($this->restaurant->{$slot['column']}),
                'is_pdf' => MediaPlacement::isPassthrough($this->restaurant->{$slot['column']}),
            ];
        }

        return [
            'type' => 'restaurant_document_updated',
            'title' => 'Restaurant documents '.$this->verb(),
            'message' => $this->fullName($this->restaurant).' '.$this->verb().' identity documents for verification.',
            'role' => $this->restaurant->role,
            'user_id' => $this->restaurant->id,
            'user' => $this->personalDetails($this->restaurant),
            'documents' => $documents,
        ];
    }

    private function verb(): string
    {
        return $this->isNew ? 'submitted' : 'updated';
    }

    /**
     * Documents are private, so the payload names the *kind* of file on record
     * rather than carrying a filename, a path, or a link an email client could
     * leak into a preview.
     */
    private function describe(?string $filename): string
    {
        if (blank($filename)) {
            return 'Not provided';
        }

        return MediaPlacement::isPassthrough($filename) ? 'PDF document' : 'Image';
    }
}
