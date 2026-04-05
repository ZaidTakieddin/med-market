<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('buyer_supplier_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('supplier_company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('payment_terms')->default('cash');
            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->boolean('is_approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['buyer_company_id', 'supplier_company_id'], 'buyer_supplier_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_supplier_relationships');
    }
};
