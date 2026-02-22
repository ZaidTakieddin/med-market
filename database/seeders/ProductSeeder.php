<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = Company::where('type', 'supplier')->get();

        if ($suppliers->isEmpty()) {
            $this->command->warn('لا توجد شركات موردة. يرجى تشغيل CompanySeeder أولاً.');
            return;
        }

        $supplier1 = $suppliers->first();
        $supplier2 = $suppliers->count() > 1 ? $suppliers->skip(1)->first() : $supplier1;
        $supplier3 = $suppliers->count() > 2 ? $suppliers->skip(2)->first() : $supplier1;
        $supplier4 = $suppliers->count() > 3 ? $suppliers->skip(3)->first() : $supplier1;

        // Get categories and manufacturers
        $categoryPainkillers = Category::where('name', 'مسكنات')->first();
        $categoryAntibiotics = Category::where('name', 'مضادات حيوية')->first();
        $categoryAntiInflammatory = Category::where('name', 'مضادات التهاب')->first();
        $categoryStomach = Category::where('name', 'معدة')->first();
        $categoryAllergy = Category::where('name', 'مضادات حساسية')->first();
        $categoryCardiovascular = Category::where('name', 'قلب وأوعية')->first();
        $categoryDiabetes = Category::where('name', 'سكري')->first();
        $categoryVitamins = Category::where('name', 'فيتامينات')->first();
        $categorySupplements = Category::where('name', 'مكملات غذائية')->first();

        $manufacturerNational = Manufacturer::where('name', 'شركة الأدوية الوطنية')->first();
        $manufacturerAdvanced = Manufacturer::where('name', 'شركة الأدوية المتقدمة')->first();
        $manufacturerHealth = Manufacturer::where('name', 'شركة المكملات الصحية')->first();

        $products = [
            // Supplier 1 Products
            [
                'supplier_company_id' => $supplier1->id,
                'name' => 'باراسيتامول 500 مجم',
                'description' => 'مسكن للآلام وخافض للحرارة، أقراص 500 مجم',
                'category_id' => $categoryPainkillers?->id,
                'manufacturer_id' => $manufacturerNational?->id,
                'sku' => 'PAR-500-001',
                'barcode' => '6224001234567',
                'unit' => 'box',
                'price' => 15.50, // Legacy
                'pharmacist_price' => 15.50,
                'customer_price' => 18.00,
                'discount' => 10.00,
                'discount_type' => 'percentage',
                'stock_quantity' => 500,
                'min_stock_level' => 50,
                'is_active' => true,
            ],
            [
                'supplier_company_id' => $supplier1->id,
                'name' => 'أموكسيسيلين 500 مجم',
                'description' => 'مضاد حيوي واسع الطيف، كبسولات 500 مجم',
                'category_id' => $categoryAntibiotics?->id,
                'manufacturer_id' => $manufacturerNational?->id,
                'sku' => 'AMX-500-001',
                'barcode' => '6224001234568',
                'unit' => 'box',
                'price' => 45.00, // Legacy
                'pharmacist_price' => 45.00,
                'customer_price' => 55.00,
                'discount' => null,
                'discount_type' => null,
                'stock_quantity' => 300,
                'min_stock_level' => 30,
                'is_active' => true,
            ],
            [
                'supplier_company_id' => $supplier1->id,
                'name' => 'إيبوبروفين 400 مجم',
                'description' => 'مضاد للالتهابات ومسكن للآلام، أقراص 400 مجم',
                'category_id' => $categoryAntiInflammatory?->id,
                'manufacturer_id' => $manufacturerNational?->id,
                'sku' => 'IBU-400-001',
                'barcode' => '6224001234569',
                'unit' => 'box',
                'price' => 22.75, // Legacy
                'pharmacist_price' => 22.75,
                'customer_price' => 28.00,
                'discount' => 5.00,
                'discount_type' => 'fixed',
                'stock_quantity' => 400,
                'min_stock_level' => 40,
                'is_active' => true,
            ],
            [
                'supplier_company_id' => $supplier1->id,
                'name' => 'أوميبرازول 20 مجم',
                'description' => 'مثبط لمضخة البروتون، كبسولات 20 مجم',
                'category_id' => $categoryStomach?->id,
                'manufacturer_id' => $manufacturerNational?->id,
                'sku' => 'OME-20-001',
                'barcode' => '6224001234570',
                'unit' => 'box',
                'price' => 35.00, // Legacy
                'pharmacist_price' => 35.00,
                'customer_price' => 42.00,
                'discount' => null,
                'discount_type' => null,
                'stock_quantity' => 250,
                'min_stock_level' => 25,
                'is_active' => true,
            ],
            [
                'supplier_company_id' => $supplier1->id,
                'name' => 'لوراتادين 10 مجم',
                'description' => 'مضاد للهيستامين، أقراص 10 مجم',
                'category_id' => $categoryAllergy?->id,
                'manufacturer_id' => $manufacturerNational?->id,
                'sku' => 'LOR-10-001',
                'barcode' => '6224001234571',
                'unit' => 'box',
                'price' => 18.50, // Legacy
                'pharmacist_price' => 18.50,
                'customer_price' => 22.00,
                'discount' => 15.00,
                'discount_type' => 'percentage',
                'stock_quantity' => 350,
                'min_stock_level' => 35,
                'is_active' => true,
            ],

            // Supplier 2 Products
            [
                'supplier_company_id' => $supplier2->id,
                'name' => 'أسبرين 100 مجم',
                'description' => 'مضاد للصفيحات، أقراص 100 مجم',
                'category_id' => $categoryCardiovascular?->id,
                'manufacturer_id' => $manufacturerAdvanced?->id,
                'sku' => 'ASP-100-001',
                'barcode' => '6224001234572',
                'unit' => 'box',
                'price' => 12.00, // Legacy
                'pharmacist_price' => 12.00,
                'customer_price' => 15.00,
                'discount' => null,
                'discount_type' => null,
                'stock_quantity' => 600,
                'min_stock_level' => 60,
                'is_active' => true,
            ],
            [
                'supplier_company_id' => $supplier2->id,
                'name' => 'سيتريزين 10 مجم',
                'description' => 'مضاد للهيستامين، أقراص 10 مجم',
                'category_id' => $categoryAllergy?->id,
                'manufacturer_id' => $manufacturerAdvanced?->id,
                'sku' => 'CET-10-001',
                'barcode' => '6224001234573',
                'unit' => 'box',
                'price' => 20.00, // Legacy
                'pharmacist_price' => 20.00,
                'customer_price' => 25.00,
                'discount' => 10.00,
                'discount_type' => 'percentage',
                'stock_quantity' => 450,
                'min_stock_level' => 45,
                'is_active' => true,
            ],
            [
                'supplier_company_id' => $supplier2->id,
                'name' => 'ميتفورمين 500 مجم',
                'description' => 'خافض لسكر الدم، أقراص 500 مجم',
                'category_id' => $categoryDiabetes?->id,
                'manufacturer_id' => $manufacturerAdvanced?->id,
                'sku' => 'MET-500-001',
                'barcode' => '6224001234574',
                'unit' => 'box',
                'price' => 28.50, // Legacy
                'pharmacist_price' => 28.50,
                'customer_price' => 35.00,
                'discount' => null,
                'discount_type' => null,
                'stock_quantity' => 320,
                'min_stock_level' => 32,
                'is_active' => true,
            ],
            [
                'supplier_company_id' => $supplier2->id,
                'name' => 'أتينولول 50 مجم',
                'description' => 'حاصرات بيتا، أقراص 50 مجم',
                'category_id' => $categoryCardiovascular?->id,
                'manufacturer_id' => $manufacturerAdvanced?->id,
                'sku' => 'ATE-50-001',
                'barcode' => '6224001234575',
                'unit' => 'box',
                'price' => 32.00, // Legacy
                'pharmacist_price' => 32.00,
                'customer_price' => 40.00,
                'discount' => 8.00,
                'discount_type' => 'fixed',
                'stock_quantity' => 280,
                'min_stock_level' => 28,
                'is_active' => true,
            ],

            // Supplier 3 Products
            [
                'supplier_company_id' => $supplier3->id,
                'name' => 'فيتامين د3 1000 وحدة',
                'description' => 'مكمل غذائي، كبسولات 1000 وحدة دولية',
                'category_id' => $categoryVitamins?->id,
                'manufacturer_id' => $manufacturerHealth?->id,
                'sku' => 'VIT-D3-001',
                'barcode' => '6224001234576',
                'unit' => 'box',
                'price' => 55.00, // Legacy
                'pharmacist_price' => 55.00,
                'customer_price' => 70.00,
                'discount' => 20.00,
                'discount_type' => 'percentage',
                'stock_quantity' => 200,
                'min_stock_level' => 20,
                'is_active' => true,
            ],
            [
                'supplier_company_id' => $supplier3->id,
                'name' => 'أوميغا 3',
                'description' => 'مكمل غذائي من زيت السمك، كبسولات 1000 مجم',
                'category_id' => $categorySupplements?->id,
                'manufacturer_id' => $manufacturerHealth?->id,
                'sku' => 'OMG-3-001',
                'barcode' => '6224001234577',
                'unit' => 'box',
                'price' => 75.00, // Legacy
                'pharmacist_price' => 75.00,
                'customer_price' => 95.00,
                'discount' => null,
                'discount_type' => null,
                'stock_quantity' => 150,
                'min_stock_level' => 15,
                'is_active' => true,
            ],
            [
                'supplier_company_id' => $supplier3->id,
                'name' => 'كولاجين + فيتامين سي',
                'description' => 'مكمل غذائي للبشرة والمفاصل',
                'category_id' => $categorySupplements?->id,
                'manufacturer_id' => $manufacturerHealth?->id,
                'sku' => 'COL-VITC-001',
                'barcode' => '6224001234578',
                'unit' => 'box',
                'price' => 120.00, // Legacy
                'pharmacist_price' => 120.00,
                'customer_price' => 150.00,
                'discount' => 25.00,
                'discount_type' => 'fixed',
                'stock_quantity' => 100,
                'min_stock_level' => 10,
                'is_active' => true,
            ],

            // Supplier 4 Products (ensure supplier company id 4 has products)
            [
                'supplier_company_id' => $supplier4->id,
                'name' => 'فيتامين ب12 1000 مكروجرام',
                'description' => 'مكمل فيتامين ب12، أقراص تحت اللسان',
                'category_id' => $categoryVitamins?->id,
                'manufacturer_id' => $manufacturerHealth?->id,
                'sku' => 'VIT-B12-001',
                'barcode' => '6224001234579',
                'unit' => 'box',
                'price' => 42.00, // Legacy
                'pharmacist_price' => 42.00,
                'customer_price' => 52.00,
                'discount' => 10.00,
                'discount_type' => 'percentage',
                'stock_quantity' => 180,
                'min_stock_level' => 18,
                'is_active' => true,
            ],
            [
                'supplier_company_id' => $supplier4->id,
                'name' => 'ماغنيسيوم + زنك',
                'description' => 'مكمل غذائي للماغنيسيوم والزنك',
                'category_id' => $categorySupplements?->id,
                'manufacturer_id' => $manufacturerHealth?->id,
                'sku' => 'MAG-ZN-001',
                'barcode' => '6224001234580',
                'unit' => 'box',
                'price' => 38.00, // Legacy
                'pharmacist_price' => 38.00,
                'customer_price' => 48.00,
                'discount' => null,
                'discount_type' => null,
                'stock_quantity' => 220,
                'min_stock_level' => 22,
                'is_active' => true,
            ],
        ];

        foreach ($products as $productData) {
            Product::updateOrCreate(
                ['sku' => $productData['sku']],
                $productData
            );
        }

        // Sync categories and manufacturers to suppliers based on their products
        foreach ($suppliers as $supplier) {
            $categoryIds = Product::where('supplier_company_id', $supplier->id)
                ->whereNotNull('category_id')
                ->distinct()
                ->pluck('category_id')
                ->filter()
                ->values()
                ->toArray();

            $manufacturerIds = Product::where('supplier_company_id', $supplier->id)
                ->whereNotNull('manufacturer_id')
                ->distinct()
                ->pluck('manufacturer_id')
                ->filter()
                ->values()
                ->toArray();

            $supplier->categories()->sync($categoryIds);
            $supplier->manufacturers()->sync($manufacturerIds);
        }

        $this->command->info('تم تحديث/إنشاء ' . count($products) . ' منتج بنجاح.');
    }
}
