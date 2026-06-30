<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('logo_disk')->nullable();
                $table->string('logo_path')->nullable();
                $table->string('logo_url')->nullable();
            });

            return;
        }

        DB::statement('ALTER TABLE companies ADD COLUMN IF NOT EXISTS logo_disk VARCHAR(255) NULL');
        DB::statement('ALTER TABLE companies ADD COLUMN IF NOT EXISTS logo_path VARCHAR(255) NULL');
        DB::statement('ALTER TABLE companies ADD COLUMN IF NOT EXISTS logo_url VARCHAR(255) NULL');
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['logo_disk', 'logo_path', 'logo_url']);
        });
    }
};
