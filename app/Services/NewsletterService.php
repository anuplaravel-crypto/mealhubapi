<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\NewsletterSubscriber;
use App\Notifications\NewsletterConfirmationNotification;
use App\Repositories\NewsletterSubscriberRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Newsletter signups, double opt-in.
 *
 * Flat under `app/Services/` like `AuthService`, `LocationService`,
 * `ProfileService` and `NotificationService` — the roadmap's
 * `Services/Newsletter/` path is MealHub's, and the convention here is that a
 * sub-namespace arrives when a domain has several classes. This one has one.
 *
 * The whole domain turns on a single rule: **an address is not on the list
 * until the person who owns it says so.** Rate limiting stops volume, not
 * impersonation — anyone can type anyone else's email into a public form — so
 * confirmation is what stands between this feature and mailing strangers.
 */
class NewsletterService
{
    /**
     * Rows per page of the admin list.
     *
     * Fixed rather than read from `?per_page=`, the same call as
     * {@see NotificationService}: a client-supplied page size is validated
     * input, and the admin surface here has no Form Request. Phase 11's
     * `Admin/ListUsersRequest` is where paging parameters get one.
     */
    private const PER_PAGE = 20;

    public function __construct(
        private readonly NewsletterSubscriberRepository $subscribers,
    ) {}

    /**
     * Take a signup and send the confirmation mail.
     *
     * Returns nothing on purpose. The caller must answer identically whether
     * the address was new, already pending, or already confirmed — a response
     * that distinguishes them turns this public, unauthenticated endpoint into
     * an oracle for "is this person subscribed?", which is a disclosure the
     * subscriber never agreed to. Everything the caller needs to say is the
     * same in all three cases: check your inbox.
     *
     * Resubscribing after unsubscribing clears the opt-out and re-confirms from
     * scratch rather than silently reinstating: the unsubscribe may well have
     * been the address's real owner, so consent is asked for again.
     */
    public function subscribe(string $email): void
    {
        $email = Str::lower($email);
        $subscriber = $this->subscribers->findByEmail($email);

        if ($subscriber === null) {
            $subscriber = $this->subscribers->create([
                'email' => $email,
                'token' => $this->freshToken(),
            ]);
        } elseif ($subscriber->is_mailable) {
            // Already a confirmed subscriber. Sending another confirmation
            // would let anyone mail an arbitrary address on demand simply by
            // resubmitting it, so this stops here — silently, for the same
            // enumeration reason described above.
            return;
        } else {
            $subscriber = $this->subscribers->update($subscriber, [
                'confirmed_at' => null,
                'unsubscribed_at' => null,
            ]);
        }

        $subscriber->notify(new NewsletterConfirmationNotification($subscriber->token));
    }

    /**
     * Confirm a signup from its emailed link.
     *
     * Idempotent: a second call with the same token succeeds and leaves the
     * original `confirmed_at` alone. Mail clients prefetch links and people
     * forward them, so a repeat is a normal event, and moving the timestamp
     * would destroy the only thing it records — when consent was actually
     * given.
     *
     * @throws DomainException 404 when the token was never issued
     */
    public function confirm(string $token): NewsletterSubscriber
    {
        $subscriber = $this->findByTokenOrFail($token);

        if ($subscriber->confirmed_at === null || $subscriber->unsubscribed_at !== null) {
            $subscriber = $this->subscribers->update($subscriber, [
                'confirmed_at' => $subscriber->confirmed_at ?? now(),
                'unsubscribed_at' => null,
            ]);
        }

        return $subscriber;
    }

    /**
     * Unsubscribe from an emailed link.
     *
     * The row is kept rather than deleted, so a later signup for the same
     * address cannot quietly resurrect a membership its owner opted out of —
     * the opt-out is a fact worth remembering. Admin deletion is the separate
     * path, for an actual erasure request.
     *
     * @throws DomainException 404 when the token was never issued
     */
    public function unsubscribe(string $token): NewsletterSubscriber
    {
        $subscriber = $this->findByTokenOrFail($token);

        if ($subscriber->unsubscribed_at === null) {
            $subscriber = $this->subscribers->update($subscriber, ['unsubscribed_at' => now()]);
        }

        return $subscriber;
    }

    /**
     * One page of the admin list, newest first.
     *
     * @return LengthAwarePaginator<int, NewsletterSubscriber>
     */
    public function paginate(): LengthAwarePaginator
    {
        return $this->subscribers->paginateLatest(self::PER_PAGE);
    }

    /**
     * Erase a subscriber outright, for an erasure request.
     *
     * Distinct from unsubscribing, which the subscriber does themselves and
     * which keeps the row so the opt-out is remembered. This forgets the
     * address entirely, which means a later signup for it starts clean.
     */
    public function delete(NewsletterSubscriber $subscriber): void
    {
        $this->subscribers->delete($subscriber);
    }

    /**
     * Resolve an emailed token, or fail with the one message both public link
     * endpoints give.
     *
     * A 404 rather than a 422: the token names a row, and naming one that does
     * not exist is a not-found, not a malformed request. The message says
     * nothing about which of "never issued", "mistyped" or "already erased"
     * applies — the client renders it verbatim on a page opened from an email.
     *
     * @throws DomainException
     */
    private function findByTokenOrFail(string $token): NewsletterSubscriber
    {
        $subscriber = $this->subscribers->findByToken($token);

        if ($subscriber === null) {
            throw new DomainException('This link is not valid. It may have already been used, or never issued.', 404);
        }

        return $subscriber;
    }

    /**
     * A token unique across the table.
     *
     * The column is unique, so a collision would surface as a database
     * exception on an otherwise valid signup. At 64 random characters that is
     * vanishingly unlikely, but the loop costs one indexed lookup and removes
     * the possibility entirely.
     */
    private function freshToken(): string
    {
        do {
            $token = Str::random(64);
        } while ($this->subscribers->tokenExists($token));

        return $token;
    }
}
