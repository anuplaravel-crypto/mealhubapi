<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\County;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Seed a small country → county → city tree so the registration
     * cascade has data to display.
     *
     * @var array<string, array<string, list<string>>>
     */
    private array $tree = [
        'United Kingdom' => [
            'Greater London' => ['London', 'Croydon', 'Bromley'],
            'Greater Manchester' => ['Manchester', 'Bolton', 'Salford'],
            'West Midlands' => ['Birmingham', 'Coventry', 'Wolverhampton'],
        ],
        'United States' => [
            'California' => ['Los Angeles', 'San Francisco', 'San Diego'],
            'Texas' => ['Houston', 'Austin', 'Dallas'],
            'New York' => ['New York City', 'Buffalo', 'Albany'],
        ],
        'Bangladesh' => [
            'Dhaka' => ['Dhaka', 'Gazipur', 'Narayanganj'],
            'Chattogram' => ['Chattogram', "Cox's Bazar", 'Comilla'],
        ],
    ];

    public function run(): void
    {
        foreach ($this->tree as $countryName => $counties) {
            $country = Country::firstOrCreate(['name' => $countryName]);

            foreach ($counties as $countyName => $cities) {
                $county = County::firstOrCreate([
                    'name' => $countyName,
                    'country_id' => $country->id,
                ]);

                foreach ($cities as $cityName) {
                    City::firstOrCreate([
                        'name' => $cityName,
                        'county_id' => $county->id,
                    ]);
                }
            }
        }
    }
}
