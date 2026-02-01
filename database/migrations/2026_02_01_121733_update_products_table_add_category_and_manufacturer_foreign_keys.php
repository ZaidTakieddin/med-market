<?php

use App\Models\Category;
use App\Models\Manufacturer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::table('products', function (Blueprint $table) {
            // Add foreign key columns
            $table->foreignId('category_id')->nullable()->after('description')->constrained()->onDelete('set null');
            $table->foreignId('manufacturer_id')->nullable()->after('category_id')->constrained()->onDelete('set null');
        });

        // Migrate existing category string data to category_id
        $this->migrateCategories();

        // Drop old category column and index
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }

    /**
     * Migrate existing category strings to category_id.
     */
    protected function migrateCategories(): void
    {
        // Get all unique category values from products
        $categories = DB::table('products')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->unique();

        // Create categories from existing strings
        foreach ($categories as $categoryName) {
            $category = Category::firstOrCreate(
                ['name' => $categoryName],
                ['is_active' => true]
            );

            // Update products with this category name to use the category_id
            DB::table('products')
                ->where('category', $categoryName)
                ->update(['category_id' => $category->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back category string column
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->nullable()->after('description');
            $table->index('category');
        });

        // Migrate category_id back to category string
        $products = DB::table('products')
            ->whereNotNull('category_id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('products.id', 'categories.name')
            ->get();

        foreach ($products as $product) {
            DB::table('products')
                ->where('id', $product->id)
                ->update(['category' => $product->name]);
        }

        // Drop foreign key columns
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['manufacturer_id']);
            $table->dropColumn(['category_id', 'manufacturer_id']);
        });
    }
};
