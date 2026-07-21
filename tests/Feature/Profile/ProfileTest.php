<?php

namespace Tests\Feature\Profile;

use App\Models\City;
use App\Models\Country;
use App\Models\County;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The read and write halves of a user's own account details.
 *
 * These endpoints take no id, so what needs pinning is that they can only ever
 * reach the token holder's row — including when the payload says otherwise.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: string}
     */
    private function signedInUser(string $role = 'customer'): array
    {
        $user = User::factory()->{$role}()->create();

        return [$user, $user->createToken('auth-token')->plainTextToken];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'mobile' => '01234567890',
        ], $overrides);
    }

    public function test_a_signed_in_user_reads_their_own_profile(): void
    {
        [$user, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/profile')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', 'customer');
    }

    public function test_the_profile_carries_the_hydrated_location_rows(): void
    {
        $country = Country::factory()->create();
        $county = County::factory()->forCountry($country)->create();
        $city = City::factory()->forCounty($county)->create();

        $user = User::factory()->create([
            'country_id' => $country->id,
            'county_id' => $county->id,
            'city_id' => $city->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$user->createToken('auth-token')->plainTextToken}")
            ->getJson('/api/v1/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.country.name', $country->name)
            ->assertJsonPath('data.county.name', $county->name)
            ->assertJsonPath('data.city.name', $city->name);
    }

    public function test_a_profile_without_a_location_reports_nulls_rather_than_omitting_the_keys(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.country', null)
            ->assertJsonPath('data.county', null)
            ->assertJsonPath('data.city', null)
            ->assertJsonPath('data.image_url', null);
    }

    /**
     * The three location rows are eager-loaded, so reading a profile is a fixed
     * number of queries — not one plus three per relation resolved lazily by
     * the Resource.
     */
    public function test_reading_a_profile_does_not_query_per_relation(): void
    {
        $city = City::factory()->create();

        $user = User::factory()->create([
            'country_id' => $city->county->country_id,
            'county_id' => $city->county_id,
            'city_id' => $city->id,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        DB::enableQueryLog();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/profile')
            ->assertStatus(200);

        // Token lookup + user + one per eager-loaded relation.
        $this->assertLessThanOrEqual(6, count(DB::getQueryLog()));

        DB::disableQueryLog();
    }

    public function test_reading_a_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/profile')->assertStatus(401);
    }

    public function test_a_signed_in_user_updates_their_own_profile(): void
    {
        [$user, $token] = $this->signedInUser();
        $city = City::factory()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', $this->validPayload([
                'address1' => '12 Analytical Way',
                'zip_code' => 'EC1A 1BB',
                'country_id' => $city->county->country_id,
                'county_id' => $city->county_id,
                'city_id' => $city->id,
            ]))
            ->assertStatus(200)
            ->assertJsonPath('data.firstName', 'Ada')
            ->assertJsonPath('data.city.id', $city->id)
            ->assertJsonPath('message', 'Profile updated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'address1' => '12 Analytical Way',
            'city_id' => $city->id,
        ]);
    }

    /**
     * `load()` re-queries rather than reusing what is already attached, so the
     * response cannot describe the city the user just moved away from.
     */
    public function test_the_response_reflects_the_location_just_saved(): void
    {
        $before = City::factory()->create();
        $after = City::factory()->create();

        $user = User::factory()->create([
            'country_id' => $before->county->country_id,
            'county_id' => $before->county_id,
            'city_id' => $before->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$user->createToken('auth-token')->plainTextToken}")
            ->putJson('/api/v1/profile', $this->validPayload([
                'country_id' => $after->county->country_id,
                'county_id' => $after->county_id,
                'city_id' => $after->id,
            ]))
            ->assertStatus(200)
            ->assertJsonPath('data.city.name', $after->name);
    }

    public function test_the_account_identifier_and_administrative_flags_are_not_self_editable(): void
    {
        $user = User::factory()->customer()->inactive()->create(['email' => 'ada@example.com']);

        $this->withHeader('Authorization', "Bearer {$user->createToken('auth-token')->plainTextToken}")
            ->putJson('/api/v1/profile', $this->validPayload([
                'email' => 'someone-else@example.com',
                'role' => 'admin',
                'status' => true,
                'is_email_verified' => true,
                'image' => 'not-a-real-upload.jpg',
            ]))
            ->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'ada@example.com',
            'role' => 'customer',
            'status' => false,
            'image' => null,
        ]);
    }

    /**
     * There is no id anywhere in this endpoint — the row updated is the token's
     * user, so an id smuggled into the payload changes nothing.
     */
    public function test_a_user_cannot_reach_another_users_row(): void
    {
        [, $token] = $this->signedInUser();
        $victim = User::factory()->create(['firstName' => 'Grace']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', $this->validPayload([
                'id' => $victim->id,
                'user_id' => $victim->id,
            ]))
            ->assertStatus(200);

        $this->assertSame('Grace', $victim->fresh()->firstName);
    }

    public function test_the_required_fields_are_validated(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', ['lastName' => 'Lovelace'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.firstName.0', 'Please enter your first name.')
            ->assertJsonPath('errors.mobile.0', 'Please enter your mobile number.');
    }

    public function test_an_unknown_location_id_is_rejected(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', $this->validPayload(['city_id' => 9999]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['city_id']]);
    }

    public function test_a_value_longer_than_its_column_is_rejected(): void
    {
        [, $token] = $this->signedInUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', $this->validPayload(['firstName' => str_repeat('a', 21)]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['firstName']]);
    }

    public function test_updating_a_profile_requires_authentication(): void
    {
        $this->putJson('/api/v1/profile', $this->validPayload())->assertStatus(401);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function roleProvider(): array
    {
        return [
            'customer' => ['customer'],
            'admin' => ['admin'],
            'restaurant' => ['restaurant'],
            'rider' => ['rider'],
        ];
    }

    /**
     * One case per role rather than a loop: the sanctum guard memoizes the
     * resolved user for the lifetime of a test method, so a second request as a
     * different user would still be seen as the first.
     */
    #[DataProvider('roleProvider')]
    public function test_every_role_maintains_its_own_profile(string $role): void
    {
        [$user, $token] = $this->signedInUser($role);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', $this->validPayload())
            ->assertStatus(200)
            ->assertJsonPath('data.role', $role);

        $this->assertSame('Ada', $user->fresh()->firstName);
    }
}
