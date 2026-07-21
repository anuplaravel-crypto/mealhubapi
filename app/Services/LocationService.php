<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
use App\Models\County;
use App\Repositories\LocationRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Serves the cascading location dropdowns. The cascade is shared by all four
 * roles' registration forms, so this stays role-agnostic — and the data is
 * public reference data, so there is nothing to authorize here.
 */
class LocationService
{
    public function __construct(
        private readonly LocationRepository $locations,
    ) {}

    /**
     * @return Collection<int, Country>
     */
    public function countries(): Collection
    {
        return $this->locations->countries();
    }

    /**
     * @return Collection<int, County>
     */
    public function countiesForCountry(Country $country): Collection
    {
        return $this->locations->countiesForCountry($country);
    }

    /**
     * @return Collection<int, City>
     */
    public function citiesForCounty(County $county): Collection
    {
        return $this->locations->citiesForCounty($county);
    }
}
