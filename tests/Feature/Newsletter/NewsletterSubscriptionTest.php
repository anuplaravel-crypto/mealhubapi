<?php

namespace Tests\Feature\Newsletter;

use App\Models\NewsletterSubscriber;
use App\Notifications\NewsletterConfirmationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The three public newsletter endpoints.
 *
 * Two things need pinning harder than the happy path. First, **the signup
 * endpoint must not become a membership oracle** — new, pending and already
 * confirmed all have to answer byte-identically, or an anonymous caller can
 * ask "is this person subscribed?" about anyone. Second, **an address is not
 * on the list until its owner says so**, which is the entire reason the token
 * round trip exists.
 */
class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNUP_MESSAGE = 'Almost there — check your inbox to confirm.';

    public function test_a_signup_stores_a_pending_subscriber_and_sends_the_confirmation(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'reader@example.com'])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', self::SIGNUP_MESSAGE);

        $subscriber = NewsletterSubscriber::first();

        // Not a subscriber yet — that is the whole point of double opt-in.
        $this->assertNotNull($subscriber);
        $this->assertNull($subscriber->confirmed_at);
        $this->assertFalse($subscriber->is_mailable);
        $this->assertSame('pending', $subscriber->status);

        Notification::assertSentTo($subscriber, NewsletterConfirmationNotification::class);
    }

    public function test_an_email_is_stored_lowercase_in_a_single_row(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'Reader@Example.COM'])
            ->assertStatus(200);

        // The casing convention for lookup values, and what stops one address
        // occupying two rows.
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'reader@example.com']);
        $this->assertDatabaseCount('newsletter_subscribers', 1);
    }

    public function test_a_malformed_email_is_rejected(): void
    {
        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_a_missing_email_is_rejected(): void
    {
        $this->postJson('/api/v1/newsletter/subscribe', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_signing_up_twice_is_answered_identically_and_creates_one_row(): void
    {
        Notification::fake();

        $first = $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'reader@example.com'])
            ->assertStatus(200);

        $second = $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'reader@example.com'])
            ->assertStatus(200);

        // Byte-identical: a different message for "already on the list" would
        // turn this public endpoint into a membership oracle.
        $this->assertSame($first->json(), $second->json());
        $this->assertDatabaseCount('newsletter_subscribers', 1);
    }

    public function test_resubmitting_a_confirmed_address_sends_no_second_email(): void
    {
        Notification::fake();
        $subscriber = NewsletterSubscriber::factory()->confirmed()->create(['email' => 'reader@example.com']);

        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'reader@example.com'])
            ->assertStatus(200)
            // Still the same sentence — the response must not admit that the
            // address is already subscribed.
            ->assertJsonPath('message', self::SIGNUP_MESSAGE);

        // Otherwise anyone could mail an arbitrary confirmed address on demand
        // just by resubmitting it.
        Notification::assertNothingSent();
        $this->assertTrue($subscriber->fresh()->is_mailable);
    }

    public function test_the_confirmation_email_links_at_the_frontend_not_the_api(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://app.mealhub.test/']);

        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'reader@example.com'])
            ->assertStatus(200);

        $subscriber = NewsletterSubscriber::first();

        Notification::assertSentTo(
            $subscriber,
            function (NewsletterConfirmationNotification $notification) use ($subscriber): bool {
                $mail = $notification->toMail($subscriber);

                // A recipient who lands on the API gets raw JSON and no way to
                // act on it. The trailing slash on the configured base must not
                // produce a doubled one either.
                $this->assertSame(
                    "https://app.mealhub.test/newsletter/confirm/{$subscriber->token}",
                    $mail->actionUrl,
                );

                // The opt-out is offered without confirming first: the address
                // may have been typed in by somebody else.
                $this->assertStringContainsString(
                    "https://app.mealhub.test/newsletter/unsubscribe/{$subscriber->token}",
                    implode(' ', $mail->introLines).' '.implode(' ', $mail->outroLines),
                );

                return true;
            },
        );
    }

    public function test_confirming_makes_a_subscriber_mailable(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->postJson("/api/v1/newsletter/confirm/{$subscriber->token}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', $subscriber->email)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.is_mailable', true);

        $this->assertTrue($subscriber->fresh()->is_mailable);
    }

    public function test_confirming_twice_succeeds_without_moving_the_timestamp(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->postJson("/api/v1/newsletter/confirm/{$subscriber->token}")->assertStatus(200);
        $confirmedAt = $subscriber->fresh()->confirmed_at;

        // Mail clients prefetch links and people forward them, so a repeat is a
        // normal event — and `confirmed_at` records when consent was given,
        // which a retry must not overwrite.
        $this->postJson("/api/v1/newsletter/confirm/{$subscriber->token}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertEquals($confirmedAt, $subscriber->fresh()->confirmed_at);
    }

    public function test_an_unknown_confirm_token_is_a_404(): void
    {
        $this->postJson('/api/v1/newsletter/confirm/'.str_repeat('z', 64))
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This link is not valid. It may have already been used, or never issued.');
    }

    public function test_an_unknown_unsubscribe_token_is_a_404(): void
    {
        $this->postJson('/api/v1/newsletter/unsubscribe/'.str_repeat('z', 64))
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_unsubscribing_stops_mail_but_keeps_the_row(): void
    {
        $subscriber = NewsletterSubscriber::factory()->confirmed()->create();

        $this->postJson("/api/v1/newsletter/unsubscribe/{$subscriber->token}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'unsubscribed')
            ->assertJsonPath('data.is_mailable', false);

        $subscriber->refresh();

        // The row survives so a later signup cannot quietly resurrect a
        // membership the owner opted out of.
        $this->assertFalse($subscriber->is_mailable);
        $this->assertNotNull($subscriber->unsubscribed_at);
        $this->assertDatabaseCount('newsletter_subscribers', 1);
    }

    public function test_unsubscribing_twice_succeeds_without_moving_the_timestamp(): void
    {
        $subscriber = NewsletterSubscriber::factory()->unsubscribed()->create();
        $unsubscribedAt = $subscriber->unsubscribed_at;

        $this->postJson("/api/v1/newsletter/unsubscribe/{$subscriber->token}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'unsubscribed');

        $this->assertEquals($unsubscribedAt, $subscriber->fresh()->unsubscribed_at);
    }

    public function test_the_token_still_works_after_confirming(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->postJson("/api/v1/newsletter/confirm/{$subscriber->token}")->assertStatus(200);

        // The token is never cleared, because every mailing has to carry a
        // working unsubscribe link. Clearing it on confirm would strand
        // confirmed subscribers with no way out.
        $this->assertSame($subscriber->token, $subscriber->fresh()->token);

        $this->postJson("/api/v1/newsletter/unsubscribe/{$subscriber->token}")->assertStatus(200);
        $this->assertFalse($subscriber->fresh()->is_mailable);
    }

    public function test_resubscribing_after_unsubscribing_requires_confirming_again(): void
    {
        Notification::fake();
        $subscriber = NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'reader@example.com']);

        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'reader@example.com'])
            ->assertStatus(200);

        $subscriber->refresh();

        // The opt-out may well have been the address's real owner, so consent
        // is asked for again rather than silently reinstated.
        $this->assertNull($subscriber->confirmed_at);
        $this->assertNull($subscriber->unsubscribed_at);
        $this->assertFalse($subscriber->is_mailable);
        Notification::assertSentTo($subscriber, NewsletterConfirmationNotification::class);
    }

    public function test_the_token_is_never_exposed_in_a_response(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        $response = $this->postJson("/api/v1/newsletter/confirm/{$subscriber->token}")->assertStatus(200);

        // It is a bearer credential: whoever holds it can confirm or
        // unsubscribe that address. It belongs in the email and nowhere else.
        $this->assertArrayNotHasKey('token', $response->json('data'));
        $this->assertStringNotContainsString($subscriber->token, $response->getContent());
    }

    public function test_signups_are_rate_limited(): void
    {
        Notification::fake();

        // Five a minute is the configured allowance.
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/newsletter/subscribe', ['email' => "reader{$i}@example.com"])
                ->assertStatus(200);
        }

        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'reader6@example.com'])
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'retry_after']);

        // The sixth address was never stored or mailed.
        $this->assertDatabaseCount('newsletter_subscribers', 5);
    }
}
