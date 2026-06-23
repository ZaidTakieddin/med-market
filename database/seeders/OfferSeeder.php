<?php

namespace Database\Seeders;

use App\Constants\OfferType;
use App\Models\Company;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OfferSeeder extends Seeder
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

        // Get products for each supplier
        $supplier1Products = Product::where('supplier_company_id', $supplier1->id)->get();
        $supplier2Products = Product::where('supplier_company_id', $supplier2->id)->get();
        $supplier3Products = Product::where('supplier_company_id', $supplier3->id)->get();

        if ($supplier1Products->isEmpty() && $supplier2Products->isEmpty() && $supplier3Products->isEmpty()) {
            $this->command->warn('لا توجد منتجات. يرجى تشغيل ProductSeeder أولاً.');

            return;
        }

        $offers = [];

        // Supplier 1 Offers
        if ($supplier1Products->isNotEmpty()) {
            $product1 = $supplier1Products->first();
            $product2 = $supplier1Products->count() > 1 ? $supplier1Products->skip(1)->first() : $product1;

            // Buy X Get Y Offer
            $offer1 = Offer::create([
                'supplier_company_id' => $supplier1->id,
                'name' => 'اشتري 2 واحصل على 1 مجاناً - باراسيتامول',
                'description' => 'عرض خاص على باراسيتامول: اشتري علبتين واحصل على علبة ثالثة مجاناً',
                'offer_type' => OfferType::BUY_X_GET_Y,
                'discount_value' => null,
                'quantity_required' => 2,
                'quantity_free' => 1,
                'start_date' => now()->subDays(5),
                'end_date' => now()->addDays(25),
                'is_active' => true,
            ]);
            $offer1->products()->attach([$product1->id => ['quantity_required' => 2]]);

            // Percentage Discount Offer
            $offer2 = Offer::create([
                'supplier_company_id' => $supplier1->id,
                'name' => 'خصم 20% على جميع المضادات الحيوية',
                'description' => 'خصم 20% على جميع منتجات المضادات الحيوية عند شراء 3 قطع أو أكثر',
                'offer_type' => OfferType::PERCENTAGE_DISCOUNT,
                'discount_value' => 20.00,
                'quantity_required' => 3,
                'quantity_free' => null,
                'start_date' => now()->subDays(3),
                'end_date' => now()->addDays(27),
                'is_active' => true,
            ]);
            if ($product2) {
                $offer2->products()->attach([$product2->id => ['quantity_required' => 3]]);
            }

            $offers[] = $offer1;
            $offers[] = $offer2;
        }

        // Supplier 2 Offers
        if ($supplier2Products->isNotEmpty()) {
            $product1 = $supplier2Products->first();
            $product2 = $supplier2Products->count() > 1 ? $supplier2Products->skip(1)->first() : $product1;

            // Fixed Discount Offer
            $offer3 = Offer::create([
                'supplier_company_id' => $supplier2->id,
                'name' => 'خصم 5 ريال على أدوية القلب',
                'description' => 'خصم ثابت 5 ريال على جميع أدوية القلب والأوعية الدموية عند شراء قطعتين أو أكثر',
                'offer_type' => OfferType::FIXED_DISCOUNT,
                'discount_value' => 5.00,
                'quantity_required' => 2,
                'quantity_free' => null,
                'start_date' => now()->subDays(7),
                'end_date' => now()->addDays(23),
                'is_active' => true,
            ]);
            $offer3->products()->attach([$product1->id => ['quantity_required' => 2]]);

            // Bundle Offer
            $offer4 = Offer::create([
                'supplier_company_id' => $supplier2->id,
                'name' => 'باقة أدوية السكري والقلب',
                'description' => 'عرض خاص: شراء ميتفورمين وأتينولول معاً (قطعة واحدة من كل منهما على الأقل) بخصم 15%',
                'offer_type' => OfferType::BUNDLE_PERCENTAGE,
                'discount_value' => 15.00,
                'quantity_required' => 1,
                'quantity_free' => null,
                'start_date' => now()->subDays(2),
                'end_date' => now()->addDays(28),
                'is_active' => true,
            ]);
            if ($product2) {
                $offer4->products()->attach([
                    $product1->id => ['quantity_required' => 1],
                    $product2->id => ['quantity_required' => 1],
                ]);
            }

            $offers[] = $offer3;
            $offers[] = $offer4;
        }

        // Supplier 3 Offers
        if ($supplier3Products->isNotEmpty()) {
            $product1 = $supplier3Products->first();
            $product2 = $supplier3Products->count() > 1 ? $supplier3Products->skip(1)->first() : $product1;

            // Percentage Discount on Vitamins
            $offer5 = Offer::create([
                'supplier_company_id' => $supplier3->id,
                'name' => 'خصم 25% على جميع الفيتامينات',
                'description' => 'عرض خاص: خصم 25% على جميع منتجات الفيتامينات والمكملات الغذائية عند شراء قطعتين أو أكثر',
                'offer_type' => OfferType::PERCENTAGE_DISCOUNT,
                'discount_value' => 25.00,
                'quantity_required' => 2,
                'quantity_free' => null,
                'start_date' => now()->subDays(10),
                'end_date' => now()->addDays(20),
                'is_active' => true,
            ]);
            $offer5->products()->attach([$product1->id => ['quantity_required' => 2]]);

            // Fixed Discount on Premium Products
            if ($supplier3Products->count() > 2) {
                $product3 = $supplier3Products->skip(2)->first();
                $offer6 = Offer::create([
                    'supplier_company_id' => $supplier3->id,
                    'name' => 'خصم 30 ريال على كولاجين + فيتامين سي',
                    'description' => 'عرض محدود: خصم 30 ريال على منتج كولاجين + فيتامين سي عند شراء قطعة واحدة أو أكثر',
                    'offer_type' => OfferType::FIXED_DISCOUNT,
                    'discount_value' => 30.00,
                    'quantity_required' => 1,
                    'quantity_free' => null,
                    'start_date' => now()->subDays(1),
                    'end_date' => now()->addDays(29),
                    'is_active' => true,
                ]);
                $offer6->products()->attach([$product3->id => ['quantity_required' => 1]]);
                $offers[] = $offer6;
            }

            $offers[] = $offer5;
        }

        $this->command->info('تم إنشاء '.count($offers).' عرض بنجاح.');
    }
}
