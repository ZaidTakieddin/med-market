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
        Schema::create('offer_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('quantity_required')->nullable(); // For buy X get Y on specific product
            $table->integer('quantity_free')->nullable(); // For buy X get Y
            $table->decimal('discount_override', 10, 2)->nullable(); // Override offer discount for this product
            $table->timestamps();

            $table->unique(['offer_id', 'product_id']);
            $table->index('offer_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_products');
    }
};
