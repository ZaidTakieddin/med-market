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
            // Category field
            $table->string('category')->nullable()->after('description');

            // Dual pricing system
            $table->decimal('pharmacist_price', 10, 2)->nullable()->after('price');
            $table->decimal('customer_price', 10, 2)->nullable()->after('pharmacist_price');

            // Discount system
            $table->decimal('discount', 10, 2)->nullable()->after('customer_price');
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable()->after('discount');

            // Index for category filtering
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn([
                'category',
                'pharmacist_price',
                'customer_price',
                'discount',
                'discount_type',
            ]);
        });
    }
};
