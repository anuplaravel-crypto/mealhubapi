<?php

namespace Database\Factories;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsletterSubscriber>
 */
class NewsletterSubscriberFactory extends Factory
{
    /**
     * Defaults to pending — a row exists the moment an address is typed, but
     * it is not a subscriber until confirmed.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => Str::lower(fake()->unique()->safeEmail()),
            'token' => Str::random(64),
            'confirmed_at' => null,
            'unsubscribed_at' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'confirmed_at' => now(),
            'unsubscribed_at' => null,
        ]);
    }

    /**
     * Opted out. `confirmed_at` is left alone — the row keeps its history, and
     * unsubscribed_at is what decides mailability.
     */
    public function unsubscribed(): static
    {
        return $this->state(fn (array $attributes) => [
            'confirmed_at' => now()->subDay(),
            'unsubscribed_at' => now(),
        ]);
    }

    /**
     * Reads better than confirmed() at a call site asserting who gets mailed.
     */
    public function mailable(): static
    {
        return $this->confirmed();
    }
}
