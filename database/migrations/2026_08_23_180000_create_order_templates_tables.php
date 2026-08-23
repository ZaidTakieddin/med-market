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
        Schema::create('order_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('name', 150);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('buyer_company_id');
        });

        Schema::create('order_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_template_id')->constrained('order_templates')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['order_template_id', 'product_id'], 'order_template_items_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_template_items');
        Schema::dropIfExists('order_templates');
    }
};
