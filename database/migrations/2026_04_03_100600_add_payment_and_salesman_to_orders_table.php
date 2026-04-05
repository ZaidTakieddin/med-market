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
        DB::statement("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_method VARCHAR(255) NOT NULL DEFAULT 'cash'");
        DB::statement("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_due_date DATE NULL");
        DB::statement("ALTER TABLE orders ADD COLUMN IF NOT EXISTS placed_by_salesman_id BIGINT NULL");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.table_constraints
                    WHERE constraint_name = 'orders_placed_by_salesman_id_foreign'
                ) THEN
                    ALTER TABLE orders ADD CONSTRAINT orders_placed_by_salesman_id_foreign
                    FOREIGN KEY (placed_by_salesman_id) REFERENCES users(id) ON DELETE SET NULL;
                END IF;
            END $$
        ");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['placed_by_salesman_id']);
            $table->dropColumn(['payment_method', 'payment_due_date', 'placed_by_salesman_id']);
        });
    }
};
