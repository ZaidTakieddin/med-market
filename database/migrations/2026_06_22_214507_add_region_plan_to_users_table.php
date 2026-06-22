<?php

use App\Constants\RegionPlanType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indicate if the migration should be run within a transaction.
     *
     * Neon's pooled endpoint aborts DDL issued inside a transaction (SQLSTATE 25P02),
     * so each statement must run un-transacted.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('region_plan_type')->nullable()->default(RegionPlanType::FIXED)->after('driver_status');
            $table->json('holidays')->nullable()->after('region_plan_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['region_plan_type', 'holidays']);
        });
    }
};
