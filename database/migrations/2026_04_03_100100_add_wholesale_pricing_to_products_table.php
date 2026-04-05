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
        DB::statement("ALTER TABLE products ADD COLUMN IF NOT EXISTS wholesale_price DECIMAL(12, 2) NULL");
        DB::statement("ALTER TABLE products ADD COLUMN IF NOT EXISTS wholesale_min_qty INTEGER NULL");
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['wholesale_price', 'wholesale_min_qty']);
        });
    }
};
