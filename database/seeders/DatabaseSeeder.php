<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Use unpooled connection for seeding to avoid transaction issues
        // This ensures seeders work reliably with Neon PostgreSQL
        $originalConnection = DB::getDefaultConnection();
        
        try {
            // Switch to seeder connection if configured, otherwise use default
            if (config('database.connections.pgsql_seeder.host')) {
                DB::setDefaultConnection('pgsql_seeder');
            }

            $this->call([
                CitySeeder::class,          // First: Create cities (admin managed)
                RegionSeeder::class,        // Second: Create regions (admin managed, depends on cities)
                CompanySeeder::class,       // Third: Create companies (suppliers and buyers, can use regions)
                CategorySeeder::class,      // Fourth: Create categories (admin managed)
                ManufacturerSeeder::class,  // Fifth: Create manufacturers (admin managed)
                UserSeeder::class,          // Sixth: Create users (depends on companies)
                ProductSeeder::class,       // Seventh: Create products (depends on companies, categories, manufacturers)
                OfferSeeder::class,         // Eighth: Create offers (depends on companies and products)
                OrderSeeder::class,         // Ninth: Create orders (depends on companies, users, products)
            ]);

            $this->command->info('تم إنشاء جميع البيانات بنجاح!');
        } finally {
            // Restore original connection
            DB::setDefaultConnection($originalConnection);
        }
    }
}
