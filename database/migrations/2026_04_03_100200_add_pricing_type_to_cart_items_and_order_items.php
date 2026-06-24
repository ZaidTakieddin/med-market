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
            Schema::table('cart_items', function (Blueprint $table) {
                $table->string('pricing_type')->default('retail');
            });
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('pricing_type')->default('retail');
            });

            return;
        }

        DB::statement("ALTER TABLE cart_items ADD COLUMN IF NOT EXISTS pricing_type VARCHAR(255) NOT NULL DEFAULT 'retail'");
        DB::statement("ALTER TABLE order_items ADD COLUMN IF NOT EXISTS pricing_type VARCHAR(255) NOT NULL DEFAULT 'retail'");
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('pricing_type');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('pricing_type');
        });
    }
};
