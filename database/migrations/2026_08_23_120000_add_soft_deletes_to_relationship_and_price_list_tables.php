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
        // Credit-bearing and pricing records follow the soft-delete philosophy:
        // they are never hard-deleted, keeping an audit trail.
        Schema::table('buyer_supplier_relationships', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('price_lists', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buyer_supplier_relationships', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('price_lists', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
