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
        // Driver GPS pings (polling-based live tracking).
        Schema::create('driver_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(['driver_id', 'recorded_at']);
        });

        // Delivery proof attachments (photo / signature) per order.
        Schema::create('delivery_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type', 20)->default('photo'); // photo | signature
            $table->string('disk');
            $table->string('path');
            $table->string('url')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['order_id', 'type']);
        });

        // ETA computed when an order ships.
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('estimated_delivery_at')->nullable()->after('delivered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('estimated_delivery_at');
        });

        Schema::dropIfExists('delivery_proofs');
        Schema::dropIfExists('driver_locations');
    }
};
