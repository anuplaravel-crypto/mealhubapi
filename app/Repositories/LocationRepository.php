<?php

namespace App\Repositories;

use App\Models\City;
use App\Models\Country;
use App\Models\County;
use Illuminate\Database\Eloquent\Collection;

/**
 * Every Eloquent query behind the country -> county -> city cascade the
 * registration forms drive.
 *
 * The base model is Country, the root of the hierarchy; the two child lookups
 * query through the parent's relation rather than County/City directly, so the
 * scoping cannot be forgotten at a call site.
 *
 * Each lookup selects only the columns the cascade renders — the id, the name,
 * and the foreign key the client needs to keep a child associated with its
 * parent.
 *
 * @extends BaseRepository<Country>
 */
class LocationRepository extends BaseRepository
{
    protected function model(): string
    {
        return Country::class;
    }

    /**
     * @return Collection<int, Country>
     */
    public function countries(): Collection
    {
        return $this->query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, County>
     */
    public function countiesForCountry(Country $country): Collection
    {
        return $country->counties()
            ->orderBy('name')
            ->get(['id', 'name', 'country_id']);
    }

    /**
     * @return Collection<int, City>
     */
    public function citiesForCounty(County $county): Collection
    {
        return $county->cities()
            ->orderBy('name')
            ->get(['id', 'name', 'county_id']);
    }
}
