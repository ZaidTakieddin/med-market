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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('order_id')->constrained()->onDelete('restrict');
            $table->foreignId('buyer_company_id')->constrained('companies')->onDelete('restrict');
            $table->foreignId('supplier_company_id')->constrained('companies')->onDelete('restrict');
            $table->string('status')->default('generated'); // generated, sent, paid, overdue, cancelled
            $table->string('payment_status')->default('pending'); // pending, paid, partial, overdue, refunded
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('file_path'); // Path to invoice file in storage
            $table->string('file_format')->default('txt'); // txt, pdf, etc.
            $table->timestamp('generated_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('invoice_number');
            $table->index('order_id');
            $table->index('buyer_company_id');
            $table->index('supplier_company_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('generated_at');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
