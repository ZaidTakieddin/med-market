<?php

namespace Database\Seeders;

use App\Models\Company;
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

        $products = [
            // Supplier 1 Products
            [
                'supplier_company_id' => $supplier1->id,
                'name' => 'باراسيتامول 500 مجم',
                'description' => 'مسكن للآلام وخافض للحرارة، أقراص 500 مجم',
                'sku' => 'PAR-500-001',
                'barcode' => '6224001234567',
                'unit' => 'علبة',
                'price' => 15.50,
                'stock_quantity' => 500,
                'min_stock_level' => 50,
                'is_active' => true,
                'metadata' => ['category' => 'مسكنات', 'manufacturer' => 'شركة الأدوية الوطنية'],
            ],
            [
                'supplier_company_id' => $supplier1->id,
                'name' => 'أموكسيسيلين 500 مجم',
                'description' => 'مضاد حيوي واسع الطيف، كبسولات 500 مجم',
                'sku' => 'AMX-500-001',
                'barcode' => '6224001234568',
                'unit' => 'علبة',
                'price' => 45.00,
                'stock_quantity' => 300,
                'min_stock_level' => 30,
                'is_active' => true,
                'metadata' => ['category' => 'مضادات حيوية', 'manufacturer' => 'شركة الأدوية الوطنية'],
            ],
            [
                'supplier_company_id' => $supplier1->id,
                'name' => 'إيبوبروفين 400 مجم',
                'description' => 'مضاد للالتهابات ومسكن للآلام، أقراص 400 مجم',
                'sku' => 'IBU-400-001',
                'barcode' => '6224001234569',
                'unit' => 'علبة',
                'price' => 22.75,
                'stock_quantity' => 400,
                'min_stock_level' => 40,
                'is_active' => true,
                'metadata' => ['category' => 'مضادات التهاب', 'manufacturer' => 'شركة الأدوية الوطنية'],
            ],
            [
                'supplier_company_id' => $supplier1->id,
                'name' => 'أوميبرازول 20 مجم',
                'description' => 'مثبط لمضخة البروتون، كبسولات 20 مجم',
                'sku' => 'OME-20-001',
                'barcode' => '6224001234570',
                'unit' => 'علبة',
                'price' => 35.00,
                'stock_quantity' => 250,
                'min_stock_level' => 25,
                'is_active' => true,
                'metadata' => ['category' => 'معدة', 'manufacturer' => 'شركة الأدوية الوطنية'],
            ],
            [
                'supplier_company_id' => $supplier1->id,
                'name' => 'لوراتادين 10 مجم',
                'description' => 'مضاد للهيستامين، أقراص 10 مجم',
                'sku' => 'LOR-10-001',
                'barcode' => '6224001234571',
                'unit' => 'علبة',
                'price' => 18.50,
                'stock_quantity' => 350,
                'min_stock_level' => 35,
                'is_active' => true,
                'metadata' => ['category' => 'مضادات حساسية', 'manufacturer' => 'شركة الأدوية الوطنية'],
            ],

            // Supplier 2 Products
            [
                'supplier_company_id' => $supplier2->id,
                'name' => 'أسبرين 100 مجم',
                'description' => 'مضاد للصفيحات، أقراص 100 مجم',
                'sku' => 'ASP-100-001',
                'barcode' => '6224001234572',
                'unit' => 'علبة',
                'price' => 12.00,
                'stock_quantity' => 600,
                'min_stock_level' => 60,
                'is_active' => true,
                'metadata' => ['category' => 'قلب وأوعية', 'manufacturer' => 'شركة الأدوية المتقدمة'],
            ],
            [
                'supplier_company_id' => $supplier2->id,
                'name' => 'سيتريزين 10 مجم',
                'description' => 'مضاد للهيستامين، أقراص 10 مجم',
                'sku' => 'CET-10-001',
                'barcode' => '6224001234573',
                'unit' => 'علبة',
                'price' => 20.00,
                'stock_quantity' => 450,
                'min_stock_level' => 45,
                'is_active' => true,
                'metadata' => ['category' => 'مضادات حساسية', 'manufacturer' => 'شركة الأدوية المتقدمة'],
            ],
            [
                'supplier_company_id' => $supplier2->id,
                'name' => 'ميتفورمين 500 مجم',
                'description' => 'خافض لسكر الدم، أقراص 500 مجم',
                'sku' => 'MET-500-001',
                'barcode' => '6224001234574',
                'unit' => 'علبة',
                'price' => 28.50,
                'stock_quantity' => 320,
                'min_stock_level' => 32,
                'is_active' => true,
                'metadata' => ['category' => 'سكري', 'manufacturer' => 'شركة الأدوية المتقدمة'],
            ],
            [
                'supplier_company_id' => $supplier2->id,
                'name' => 'أتينولول 50 مجم',
                'description' => 'حاصرات بيتا، أقراص 50 مجم',
                'sku' => 'ATE-50-001',
                'barcode' => '6224001234575',
                'unit' => 'علبة',
                'price' => 32.00,
                'stock_quantity' => 280,
                'min_stock_level' => 28,
                'is_active' => true,
                'metadata' => ['category' => 'قلب وأوعية', 'manufacturer' => 'شركة الأدوية المتقدمة'],
            ],

            // Supplier 3 Products
            [
                'supplier_company_id' => $supplier3->id,
                'name' => 'فيتامين د3 1000 وحدة',
                'description' => 'مكمل غذائي، كبسولات 1000 وحدة دولية',
                'sku' => 'VIT-D3-001',
                'barcode' => '6224001234576',
                'unit' => 'علبة',
                'price' => 55.00,
                'stock_quantity' => 200,
                'min_stock_level' => 20,
                'is_active' => true,
                'metadata' => ['category' => 'فيتامينات', 'manufacturer' => 'شركة المكملات الصحية'],
            ],
            [
                'supplier_company_id' => $supplier3->id,
                'name' => 'أوميغا 3',
                'description' => 'مكمل غذائي من زيت السمك، كبسولات 1000 مجم',
                'sku' => 'OMG-3-001',
                'barcode' => '6224001234577',
                'unit' => 'علبة',
                'price' => 75.00,
                'stock_quantity' => 150,
                'min_stock_level' => 15,
                'is_active' => true,
                'metadata' => ['category' => 'مكملات غذائية', 'manufacturer' => 'شركة المكملات الصحية'],
            ],
            [
                'supplier_company_id' => $supplier3->id,
                'name' => 'كولاجين + فيتامين سي',
                'description' => 'مكمل غذائي للبشرة والمفاصل',
                'sku' => 'COL-VITC-001',
                'barcode' => '6224001234578',
                'unit' => 'علبة',
                'price' => 120.00,
                'stock_quantity' => 100,
                'min_stock_level' => 10,
                'is_active' => true,
                'metadata' => ['category' => 'مكملات غذائية', 'manufacturer' => 'شركة المكملات الصحية'],
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }

        $this->command->info('تم إنشاء ' . count($products) . ' منتج بنجاح.');
    }
}
