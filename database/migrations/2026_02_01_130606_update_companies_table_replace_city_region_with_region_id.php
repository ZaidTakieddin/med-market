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
        // Drop indexes first if they exist (using raw SQL to avoid transaction issues)
        DB::statement("DROP INDEX IF EXISTS companies_region_index");
        DB::statement("DROP INDEX IF EXISTS companies_city_index");

        // Add region_id foreign key if it doesn't exist
        if (!Schema::hasColumn('companies', 'region_id')) {
            // First add the column
            DB::statement("ALTER TABLE companies ADD COLUMN IF NOT EXISTS region_id BIGINT NULL");
            
            // Then add the foreign key constraint
            $fkExists = DB::selectOne(
                "SELECT EXISTS (
                    SELECT 1 FROM information_schema.table_constraints 
                    WHERE constraint_name = 'companies_region_id_foreign' 
                    AND table_name = 'companies'
                ) as exists"
            );

            if (!$fkExists->exists) {
                DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_region_id_foreign FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL");
            }
        }

        // Drop old city and region string columns if they exist
        if (Schema::hasColumn('companies', 'city')) {
            DB::statement("ALTER TABLE companies DROP COLUMN IF EXISTS city");
        }
        if (Schema::hasColumn('companies', 'region')) {
            DB::statement("ALTER TABLE companies DROP COLUMN IF EXISTS region");
        }

        // Add index for region_id
        if (Schema::hasColumn('companies', 'region_id')) {
            $indexExists = DB::selectOne(
                "SELECT EXISTS (
                    SELECT 1 FROM pg_indexes 
                    WHERE tablename = 'companies' AND indexname = 'companies_region_id_index'
                ) as exists"
            );

            if (!$indexExists->exists) {
                DB::statement("CREATE INDEX companies_region_id_index ON companies (region_id)");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['region_id']);
            $table->dropForeign(['region_id']);
            $table->dropColumn('region_id');
            
            // Restore old columns
            $table->string('city')->nullable()->after('longitude');
            $table->string('region')->nullable()->after('city');
        });
    }
};
