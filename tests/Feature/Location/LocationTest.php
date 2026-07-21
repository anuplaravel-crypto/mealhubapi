<?php

namespace Tests\Feature\Location;

use App\Models\City;
use App\Models\Country;
use App\Models\County;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The public country -> county -> city cascade. Public reference data, so the
 * only failure paths are an unknown parent (404) and a parent with no children
 * (an empty list, not a 404) — there is nothing to validate and nothing to
 * authorize.
 */
class LocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_countries_alphabetically(): void
    {
        Country::factory()->create(['name' => 'Zambia']);
        Country::factory()->create(['name' => 'Andorra']);
        Country::factory()->create(['name' => 'Mexico']);

        $response = $this->getJson('/api/v1/countries')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['success', 'data' => [['id', 'name']]]);

        $this->assertSame(['Andorra', 'Mexico', 'Zambia'], $response->json('data.*.name'));
    }

    public function test_it_returns_an_empty_list_when_no_countries_exist(): void
    {
        $this->getJson('/api/v1/countries')
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => []]);
    }

    public function test_it_lists_the_counties_of_a_country_alphabetically(): void
    {
        $country = Country::factory()->create();
        County::factory()->forCountry($country)->create(['name' => 'Yorkshire']);
        County::factory()->forCountry($country)->create(['name' => 'Cornwall']);

        $response = $this->getJson("/api/v1/countries/{$country->id}/counties")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.country_id', $country->id)
            ->assertJsonStructure(['success', 'data' => [['id', 'name', 'country_id']]]);

        $this->assertSame(['Cornwall', 'Yorkshire'], $response->json('data.*.name'));
    }

    public function test_it_excludes_counties_belonging_to_another_country(): void
    {
        $country = Country::factory()->create();
        $other = Country::factory()->create();

        County::factory()->forCountry($country)->create(['name' => 'Kept']);
        County::factory()->forCountry($other)->create(['name' => 'Excluded']);

        $this->getJson("/api/v1/countries/{$country->id}/counties")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kept');
    }

    public function test_a_country_with_no_counties_returns_an_empty_list_not_a_404(): void
    {
        $country = Country::factory()->create();

        $this->getJson("/api/v1/countries/{$country->id}/counties")
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => []]);
    }

    public function test_it_404s_for_an_unknown_country(): void
    {
        $this->getJson('/api/v1/countries/999999/counties')
            ->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'Resource not found.']);
    }

    public function test_it_lists_the_cities_of_a_county_alphabetically(): void
    {
        $county = County::factory()->create();
        City::factory()->forCounty($county)->create(['name' => 'Salford']);
        City::factory()->forCounty($county)->create(['name' => 'Bolton']);

        $response = $this->getJson("/api/v1/counties/{$county->id}/cities")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.county_id', $county->id)
            ->assertJsonStructure(['success', 'data' => [['id', 'name', 'county_id']]]);

        $this->assertSame(['Bolton', 'Salford'], $response->json('data.*.name'));
    }

    public function test_it_excludes_cities_belonging_to_another_county(): void
    {
        $county = County::factory()->create();
        $other = County::factory()->create();

        City::factory()->forCounty($county)->create(['name' => 'Kept']);
        City::factory()->forCounty($other)->create(['name' => 'Excluded']);

        $this->getJson("/api/v1/counties/{$county->id}/cities")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kept');
    }

    public function test_a_county_with_no_cities_returns_an_empty_list_not_a_404(): void
    {
        $county = County::factory()->create();

        $this->getJson("/api/v1/counties/{$county->id}/cities")
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => []]);
    }

    public function test_it_404s_for_an_unknown_county(): void
    {
        $this->getJson('/api/v1/counties/999999/cities')
            ->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'Resource not found.']);
    }

    /**
     * The lists have no relations to load, so each endpoint must stay flat in
     * the row count: one query for the list, plus the route-model binding
     * lookup on the two nested endpoints.
     */
    public function test_the_endpoints_do_not_scale_their_query_count_with_the_row_count(): void
    {
        $country = Country::factory()->withCascade(counties: 5, cities: 5)->create();
        $county = $country->counties()->first();

        $this->assertSame(1, $this->queriesFor('/api/v1/countries'));
        $this->assertSame(2, $this->queriesFor("/api/v1/countries/{$country->id}/counties"));
        $this->assertSame(2, $this->queriesFor("/api/v1/counties/{$county->id}/cities"));
    }

    /**
     * Number of queries a successful GET of the given endpoint runs.
     */
    private function queriesFor(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson($uri)->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
