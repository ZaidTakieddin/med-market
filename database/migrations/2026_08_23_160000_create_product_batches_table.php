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
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'expiry_date']);
        });

        // One bucket per batch per product; a single null-batch row holds unbatched
        // stock. Expression indexes for the NULL case (Postgres + SQLite).
        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            Schema::getConnection()->statement(
                'CREATE UNIQUE INDEX product_batches_product_batch_unique'.
                ' ON product_batches (product_id, batch_number) WHERE batch_number IS NOT NULL'
            );
            Schema::getConnection()->statement(
                'CREATE UNIQUE INDEX product_batches_product_unbatched_unique'.
                ' ON product_batches (product_id) WHERE batch_number IS NULL'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql' || Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::getConnection()->statement('DROP INDEX IF EXISTS product_batches_product_batch_unique');
            Schema::getConnection()->statement('DROP INDEX IF EXISTS product_batches_product_unbatched_unique');
        }

        Schema::dropIfExists('product_batches');
    }
};
