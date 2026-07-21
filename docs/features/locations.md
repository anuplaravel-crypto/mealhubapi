# Locations (geo reference data)

Public, read-only reference data for the **country → county → city** cascade every registration form steps through: pick a country, and the counties for that country load; pick a county, and its cities load. `users.country_id` / `county_id` / `city_id` are populated from these ids.

The data is the same for all four roles and contains nothing user-specific, so the endpoints are unauthenticated and role-agnostic.

## Endpoints

| Action | Endpoint | Auth |
| --- | --- | --- |
| List countries | `GET /api/v1/countries` | Public |
| List a country's counties | `GET /api/v1/countries/{country}/counties` | Public |
| List a county's cities | `GET /api/v1/counties/{county}/cities` | Public |

The paths are nested to express the hierarchy the client walks. MealHub's flat `cities/{countyId}` form was deliberately not carried over, nor was the `getCiiesByCounty` typo in its controller method name.

## Request / Response

- Controller: `App\Http\Controllers\Api\V1\LocationController` (`countries`, `counties`, `cities`)
- Validated by: **nothing** — the only input is a route-model-bound parent, so an unknown id fails at the binding, not at a rule. There is no Form Request for this feature by design.
- Shaped by: `App\Http\Resources\{CountryResource, CountyResource, CityResource}`
- Business logic: `App\Services\LocationService`, reading through `App\Repositories\LocationRepository`
- Envelope: the standard `{success, data}` from `App\Http\Traits\ApiResponse` — see [api-conventions.md](api-conventions.md)

Lists are **not paginated**. The whole tree is 3 countries / 8 counties / 24 cities as seeded, and a dropdown needs every option at once; a paginated cascade would force the client to page through options.

```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Greater London", "country_id": 1 },
    { "id": 2, "name": "Greater Manchester", "country_id": 1 }
  ]
}
```

Each row carries only what a dropdown renders — `id`, `name`, and (for counties and cities) the parent foreign key. The repository selects exactly those columns; timestamps are not exposed, since nothing client-side depends on when a city row was created. Counties expose `country_id` and cities expose `county_id` so a client restoring a saved address can rebuild the cascade's earlier steps without an extra lookup.

## Business Rules & Edge Cases

- **Ordering is alphabetical by name** at all three levels, not by id or insertion order — the seeded tree is not stored alphabetically, so the client must not rely on natural order.
- **A parent with no children returns `200` with an empty `data` array, not a `404`.** A country that exists but has no counties yet is a valid state, and the cascade should render an empty dropdown rather than an error.
- **An unknown parent id returns `404`** with `{"success": false, "message": "Resource not found."}` — the shared shape from `bootstrap/app.php`, which deliberately never leaks the model class name.
- **Children are scoped to their parent.** Both child lookups query through the parent's relation (`$country->counties()`, `$county->cities()`) rather than filtering `County`/`City` directly, so the scoping cannot be forgotten at a call site.
- **Deleting a parent cascades** — the `counties.country_id` and `cities.county_id` foreign keys are `cascadeOnDelete`, so removing a country removes its counties and their cities.

## Data source

`Database\Seeders\LocationSeeder` seeds 3 countries / 8 counties / 24 cities using `firstOrCreate`, so re-running it is idempotent. Row counts are pinned by `tests/Feature/Database/SeederIntegrityTest.php`.

For tests, `CountryFactory`, `CountyFactory`, and `CityFactory` build arbitrary trees; `Country::factory()->withCascade()` creates a country with counties and cities in one call, and `CountyFactory::forCountry()` / `CityFactory::forCounty()` attach a row to an existing parent.

## Tests

`tests/Feature/Location/LocationTest.php` — alphabetical ordering at each level, empty-list-not-404 for a childless parent, `404` for an unknown parent, cross-parent exclusion, and a query-count assertion (1 query for the flat list, 2 for each nested list: the binding plus the list).
