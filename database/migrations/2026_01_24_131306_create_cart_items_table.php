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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2); // Price at time of adding to cart
            $table->decimal('total_price', 10, 2); // quantity * unit_price
            $table->foreignId('applied_offer_id')->nullable()->constrained('offers')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('cart_id');
            $table->index('product_id');
            $table->index('applied_offer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
