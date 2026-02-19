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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_company_id')->constrained('companies')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('offer_type'); // 'buy_x_get_y', 'percentage_discount', 'fixed_discount', 'bundle'
            $table->decimal('discount_value', 10, 2)->nullable(); // For percentage/fixed discounts
            $table->string('discount_type')->nullable(); // 'percentage' or 'fixed'
            $table->integer('quantity_required')->nullable(); // For buy X get Y
            $table->integer('quantity_free')->nullable(); // For buy X get Y
            $table->timestamp('start_date');
            $table->timestamp('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('supplier_company_id');
            $table->index('offer_type');
            $table->index('is_active');
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
