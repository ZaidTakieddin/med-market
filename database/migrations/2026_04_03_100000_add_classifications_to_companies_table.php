<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE companies ADD COLUMN IF NOT EXISTS supplier_classification VARCHAR(255) NULL');
            DB::statement('ALTER TABLE companies ADD COLUMN IF NOT EXISTS buyer_classification VARCHAR(255) NULL');
            DB::statement('ALTER TABLE companies ADD COLUMN IF NOT EXISTS min_order_amount DECIMAL(12, 2) NULL');
        } else {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('supplier_classification')->nullable();
                $table->string('buyer_classification')->nullable();
                $table->decimal('min_order_amount', 12, 2)->nullable();
            });
        }

        DB::table('companies')
            ->where('type', 'supplier')
            ->whereNull('supplier_classification')
            ->update(['supplier_classification' => 'retail']);

        DB::table('companies')
            ->where('type', 'buyer')
            ->whereNull('buyer_classification')
            ->update(['buyer_classification' => 'pharmacy']);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['supplier_classification', 'buyer_classification', 'min_order_amount']);
        });
    }
};
