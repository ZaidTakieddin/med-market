<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indicate if the migration should be run within a transaction.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     *
     * Adds an optional weekday to each driver-region assignment so a driver can run
     * a weekly schedule (one region per weekday). A null `day_of_week` means the row
     * belongs to a fixed plan (covered every day).
     */
    public function up(): void
    {
        Schema::table('driver_regions', function (Blueprint $table) {
            $table->string('day_of_week')->nullable()->after('region_id');
        });

        // A driver covers at most one region per weekday in a daily plan. Postgres treats
        // NULLs as distinct, so fixed-plan rows (null weekday) remain unconstrained.
        Schema::table('driver_regions', function (Blueprint $table) {
            $table->dropUnique(['driver_id', 'region_id']);
            $table->unique(['driver_id', 'day_of_week']);
            $table->index('day_of_week');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_regions', function (Blueprint $table) {
            $table->dropUnique(['driver_id', 'day_of_week']);
            $table->dropIndex(['day_of_week']);
            $table->unique(['driver_id', 'region_id']);
            $table->dropColumn('day_of_week');
        });
    }
};
