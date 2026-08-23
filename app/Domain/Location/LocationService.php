<?php

namespace App\Domain\Location;

use App\Models\City;
use App\Models\Region;

class LocationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createCity(array $data): City
    {
        return City::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCity(City $city, array $data): City
    {
        $city->update($data);

        return $city->refresh();
    }

    public function deleteCity(City $city): void
    {
        $city->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRegion(array $data): Region
    {
        return Region::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRegion(Region $region, array $data): Region
    {
        $region->update($data);

        return $region->refresh();
    }

    public function deleteRegion(Region $region): void
    {
        $region->delete();
    }
}
