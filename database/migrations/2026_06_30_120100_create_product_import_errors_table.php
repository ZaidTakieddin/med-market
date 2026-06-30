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
        Schema::create('product_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_import_id')->constrained('product_imports')->onDelete('cascade');
            $table->integer('row_number');
            $table->string('sku')->nullable();
            $table->text('message');
            $table->timestamps();

            $table->index('product_import_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_import_errors');
    }
};
