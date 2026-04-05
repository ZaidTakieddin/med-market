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
                CitySeeder::class,                                  // 1: Create cities (admin managed)
                RegionSeeder::class,                                // 2: Create regions (admin managed, depends on cities)
                CompanySeeder::class,                               // 3: Create companies with classifications (suppliers + buyers + salesmen)
                CategorySeeder::class,                              // 4: Create categories (admin managed)
                ManufacturerSeeder::class,                          // 5: Create manufacturers (admin managed)
                UserSeeder::class,                                  // 6: Create users: admins, suppliers, buyers, drivers, salesmen
                ProductSeeder::class,                               // 7: Create products with wholesale pricing (depends on companies, categories, manufacturers)
                OfferSeeder::class,                                 // 8: Create offers (depends on companies and products)
                BuyerSupplierRelationshipSeeder::class,             // 9: Create buyer-supplier relationships, price lists, salesman clients
                OrderSeeder::class,                                 // 10: Create orders (depends on companies, users, products)
                SyncSupplierCategoriesAndManufacturersSeeder::class, // 11: Sync supplier categories & manufacturers
            ]);

            $this->command->info('تم إنشاء جميع البيانات بنجاح!');
        } finally {
            // Restore original connection
            DB::setDefaultConnection($originalConnection);
        }
    }
}
