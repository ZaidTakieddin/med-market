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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Actor
            $table->string('type'); // 'in', 'out', 'adjustment', 'return'
            $table->integer('quantity'); // Positive for 'in', negative for 'out'
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->text('reason')->nullable();
            $table->string('reference_type')->nullable(); // 'order', 'manual', 'adjustment', etc.
            $table->string('reference_id')->nullable();
            $table->softDeletes(); // Never hard-delete stock movements
            $table->timestamps();

            $table->index('product_id');
            $table->index('order_id');
            $table->index('user_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
