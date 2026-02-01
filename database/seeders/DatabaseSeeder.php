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
                CompanySeeder::class,      // First: Create companies (suppliers and buyers)
                CategorySeeder::class,     // Second: Create categories (admin managed)
                ManufacturerSeeder::class, // Third: Create manufacturers (admin managed)
                UserSeeder::class,          // Fourth: Create users (depends on companies)
                ProductSeeder::class,       // Fifth: Create products (depends on companies, categories, manufacturers)
                OfferSeeder::class,         // Sixth: Create offers (depends on companies and products)
                OrderSeeder::class,         // Seventh: Create orders (depends on companies, users, products)
            ]);

            $this->command->info('تم إنشاء جميع البيانات بنجاح!');
        } finally {
            // Restore original connection
            DB::setDefaultConnection($originalConnection);
        }
    }
}
