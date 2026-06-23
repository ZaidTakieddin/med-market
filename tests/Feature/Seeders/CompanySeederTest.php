<?php

namespace Tests\Feature\Seeders;

use App\Models\Company;
use Database\Seeders\CitySeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_companies_are_assigned_a_region(): void
    {
        $this->seed(CitySeeder::class);
        $this->seed(RegionSeeder::class);
        $this->seed(CompanySeeder::class);

        $this->assertGreaterThan(0, Company::count());
        $this->assertSame(
            0,
            Company::whereNull('region_id')->count(),
            'Every seeded company must be assigned a region_id (regions/driver feature depends on it).'
        );
    }
}
