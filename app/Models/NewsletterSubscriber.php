<?php

namespace App\Models;

use Database\Factories\NewsletterSubscriberFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/**
 * One newsletter signup. See the create_newsletter_subscribers_table migration
 * for why this is double opt-in and why `token` is never cleared.
 *
 * Notifiable so the confirmation mail can be sent to the row itself — Laravel
 * routes mail to `$this->email` by default, and a subscriber is deliberately
 * not a `users` row: signing up for a newsletter is not creating an account,
 * and conflating the two would put unverified addresses in the table that four
 * separate login flows authenticate against.
 */
class NewsletterSubscriber extends Model
{
    /** @use HasFactory<NewsletterSubscriberFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'token',
        'confirmed_at',
        'unsubscribed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /**
     * Whether this address may actually be mailed.
     *
     * Confirmed and not since unsubscribed — the one question every send has
     * to ask, kept here so no caller reinvents it as `whereNotNull` and forgets
     * the second half.
     */
    protected function isMailable(): Attribute
    {
        return Attribute::get(
            fn (): bool => $this->confirmed_at !== null && $this->unsubscribed_at === null
        );
    }

    /**
     * Status label, derived rather than stored so it cannot disagree with the
     * timestamps behind it.
     */
    protected function status(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->unsubscribed_at !== null) {
                return 'unsubscribed';
            }

            return $this->confirmed_at !== null ? 'confirmed' : 'pending';
        });
    }
}
