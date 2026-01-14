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
                CompanySeeder::class,  // First: Create companies (suppliers and buyers)
                UserSeeder::class,      // Second: Create users (depends on companies)
                ProductSeeder::class,   // Third: Create products (depends on companies)
                OrderSeeder::class,     // Fourth: Create orders (depends on companies, users, products)
            ]);

            $this->command->info('تم إنشاء جميع البيانات بنجاح!');
        } finally {
            // Restore original connection
            DB::setDefaultConnection($originalConnection);
        }
    }
}
