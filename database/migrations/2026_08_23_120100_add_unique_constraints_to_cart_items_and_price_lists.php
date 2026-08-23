<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

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
        $this->dedupeCartItems();
        $this->dedupePriceLists();

        Schema::table('cart_items', function (Blueprint $table) {
            // Backs the add-to-cart check-then-create flow: the same product can only
            // exist once per cart, so concurrent requests cannot duplicate rows.
            $table->unique(['cart_id', 'product_id'], 'cart_items_cart_product_unique');
        });

        $supportsPartialIndexes = in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true);

        if ($supportsPartialIndexes) {
            // Replace the relationship pair's hard unique with a live-rows-only one so a
            // soft-deleted relationship does not block creating it again later.
            Schema::table('buyer_supplier_relationships', function (Blueprint $table) {
                $table->dropUnique('buyer_supplier_unique');
            });
            DB::statement(
                'CREATE UNIQUE INDEX buyer_supplier_unique_live'.
                ' ON buyer_supplier_relationships (buyer_company_id, supplier_company_id)'.
                ' WHERE deleted_at IS NULL'
            );

            // Mirrors the app-level duplicate rule (supplier + buyer + product + min_qty,
            // where NULL min_qty compares equal) while ignoring soft-deleted entries so a
            // replaced price can be re-created.
            DB::statement(
                'CREATE UNIQUE INDEX price_lists_supplier_buyer_product_min_qty_unique'.
                ' ON price_lists (supplier_company_id, buyer_company_id, product_id, COALESCE(min_qty, -1))'.
                ' WHERE deleted_at IS NULL'
            );
        } else {
            Schema::table('price_lists', function (Blueprint $table) {
                $table->unique(
                    ['supplier_company_id', 'buyer_company_id', 'product_id', 'min_qty'],
                    'price_lists_supplier_buyer_product_min_qty_unique'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql' || Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS price_lists_supplier_buyer_product_min_qty_unique');
            DB::statement('DROP INDEX IF EXISTS buyer_supplier_unique_live');

            Schema::table('buyer_supplier_relationships', function (Blueprint $table) {
                $table->unique(['buyer_company_id', 'supplier_company_id'], 'buyer_supplier_unique');
            });
        } else {
            Schema::table('price_lists', function (Blueprint $table) {
                $table->dropUnique('price_lists_supplier_buyer_product_min_qty_unique');
            });
        }

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_cart_product_unique');
        });
    }

    /**
     * Delete duplicate cart rows (same cart + product), keeping the earliest.
     */
    private function dedupeCartItems(): void
    {
        $duplicateIds = DB::table('cart_items as ci1')
            ->join('cart_items as ci2', function (JoinClause $join): void {
                $join->on('ci1.cart_id', '=', 'ci2.cart_id')
                    ->on('ci1.product_id', '=', 'ci2.product_id')
                    ->whereColumn('ci1.id', '>', 'ci2.id');
            })
            ->pluck('ci1.id');

        if ($duplicateIds->isNotEmpty()) {
            DB::table('cart_items')->whereIn('id', $duplicateIds)->delete();
        }
    }

    /**
     * Delete duplicate price list entries (same supplier + buyer + product + min_qty,
     * with NULL min_qty comparing equal), keeping the earliest.
     */
    private function dedupePriceLists(): void
    {
        $duplicateIds = DB::table('price_lists as p1')
            ->join('price_lists as p2', function (JoinClause $join): void {
                $join->on('p1.supplier_company_id', '=', 'p2.supplier_company_id')
                    ->on('p1.buyer_company_id', '=', 'p2.buyer_company_id')
                    ->on('p1.product_id', '=', 'p2.product_id')
                    ->whereRaw('COALESCE(p1.min_qty, -1) = COALESCE(p2.min_qty, -1)')
                    ->whereColumn('p1.id', '>', 'p2.id');
            })
            ->pluck('p1.id');

        if ($duplicateIds->isNotEmpty()) {
            DB::table('price_lists')->whereIn('id', $duplicateIds)->delete();
        }
    }
};
