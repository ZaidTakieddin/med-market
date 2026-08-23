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
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Current batch info for the shelf stock; per-receipt history lives in
            // stock_movements. Nullable so non-batch-tracked suppliers are unaffected.
            $table->string('batch_number')->nullable()->after('min_stock_level');
            $table->date('expiry_date')->nullable()->after('batch_number');
            $table->index('expiry_date');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('batch_number')->nullable()->after('reference_id');
            $table->date('expiry_date')->nullable()->after('batch_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['batch_number', 'expiry_date']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['expiry_date']);
            $table->dropColumn(['batch_number', 'expiry_date']);
        });
    }
};
