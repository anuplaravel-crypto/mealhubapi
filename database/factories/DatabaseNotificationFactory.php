<?php

namespace Database\Factories;

use App\Models\User;
use App\Notifications\AccountStatusNotification;
use App\Notifications\RegistrationNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/**
 * Stored in-app notifications, for tests that need a history to read rather
 * than a delivery to assert.
 *
 * The odd one out among the factories here: its model is Laravel's
 * `DatabaseNotification`, which lives in the framework and does not use
 * `HasFactory`. `DatabaseNotification::factory()` therefore does not exist and
 * the automatic `App\Models\X` -> `XFactory` resolution never runs — call
 * `DatabaseNotificationFactory::new()` directly.
 *
 * `id` is generated here for the same reason: the `notifications` table has a
 * uuid primary key that nothing on the model fills in, because in production
 * the notification channel supplies it.
 *
 * @extends Factory<DatabaseNotification>
 */
class DatabaseNotificationFactory extends Factory
{
    /**
     * @var class-string<DatabaseNotification>
     */
    protected $model = DatabaseNotification::class;

    /**
     * Define the model's default state: an unread notification addressed to a
     * newly created customer.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => RegistrationNotification::class,
            'notifiable_type' => (new User)->getMorphClass(),
            'notifiable_id' => User::factory(),
            'data' => [
                'type' => 'customer_registration',
                'title' => 'New customer registration',
                'message' => fake()->name().' just registered as a customer.',
            ],
            'read_at' => null,
        ];
    }

    /**
     * Address the notification to an existing user.
     *
     * Named `forUser` rather than reusing the base class's `for()`, whose
     * signature this cannot narrow — and which would not work here anyway:
     * the notifiable is a morphTo, so the type column has to be written too.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now()->subHour(),
        ]);
    }

    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    /**
     * The payload {@see AccountStatusNotification} stores — the other kind
     * this phase can produce, and the one whose extras are not a `user` block.
     */
    public function accountStatus(bool $activated = true): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountStatusNotification::class,
            'data' => [
                'type' => 'account_status',
                'title' => $activated ? 'Account activated' : 'Account deactivated',
                'message' => $activated
                    ? 'Your account has been activated. You can now place meal orders.'
                    : 'Your account has been deactivated. You cannot place meal orders until it is reactivated.',
                'activated' => $activated,
            ],
        ]);
    }
}
