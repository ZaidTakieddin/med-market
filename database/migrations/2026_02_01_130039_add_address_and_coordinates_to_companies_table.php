<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indicate if the migration should be run within a transaction.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            // Portable path for the sqlite test runner. city/region are omitted —
            // the next migration replaces them with region_id.
            Schema::table('companies', function (Blueprint $table) {
                $table->decimal('latitude', 10, 8)->nullable()->index();
                $table->decimal('longitude', 11, 8)->nullable()->index();
                $table->text('address_line')->nullable();
            });

            return;
        }

        // PostgreSQL doesn't support 'after' clause, so we add columns without it
        // Use ONLY raw SQL to avoid transaction issues - no Schema::table() calls

        // Add columns using raw SQL with IF NOT EXISTS
        DB::statement('ALTER TABLE companies ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) NULL');
        DB::statement('ALTER TABLE companies ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) NULL');
        DB::statement('ALTER TABLE companies ADD COLUMN IF NOT EXISTS city VARCHAR(255) NULL');
        DB::statement('ALTER TABLE companies ADD COLUMN IF NOT EXISTS region VARCHAR(255) NULL');
        DB::statement('ALTER TABLE companies ADD COLUMN IF NOT EXISTS address_line TEXT NULL');

        // Add indexes using raw SQL with IF NOT EXISTS
        DB::statement('CREATE INDEX IF NOT EXISTS companies_latitude_index ON companies (latitude)');
        DB::statement('CREATE INDEX IF NOT EXISTS companies_longitude_index ON companies (longitude)');
        DB::statement('CREATE INDEX IF NOT EXISTS companies_city_index ON companies (city)');
        DB::statement('CREATE INDEX IF NOT EXISTS companies_region_index ON companies (region)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['region']);
            $table->dropIndex(['city']);
            $table->dropIndex(['longitude']);
            $table->dropIndex(['latitude']);

            $table->dropColumn([
                'latitude',
                'longitude',
                'city',
                'region',
                'address_line',
            ]);
        });
    }
};
