<?php

namespace Database\Seeders;

use App\Constants\PaymentTerms;
use App\Models\BuyerSupplierRelationship;
use App\Models\Company;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\SalesmanClient;
use App\Models\User;
use Illuminate\Database\Seeder;

class BuyerSupplierRelationshipSeeder extends Seeder
{
    /**
     * Seed buyer-supplier relationships, price lists, and salesman clients.
     */
    public function run(): void
    {
        $suppliers = Company::where('type', 'supplier')->get();
        $pharmacies = Company::where('type', 'buyer')->where('buyer_classification', 'pharmacy')->get();
        $salesmanCompany = Company::where('type', 'buyer')->where('buyer_classification', 'salesman')->first();

        if ($suppliers->isEmpty() || $pharmacies->isEmpty()) {
            $this->command->warn('لا توجد بيانات كافية. يرجى تشغيل CompanySeeder أولاً.');
            return;
        }

        $supplier1 = $suppliers->first();
        $supplier2 = $suppliers->skip(1)->first() ?? $supplier1;
        $supplier3 = $suppliers->skip(2)->first() ?? $supplier1;

        $pharmacy1 = $pharmacies->first();
        $pharmacy2 = $pharmacies->skip(1)->first() ?? $pharmacy1;

        $relationshipsCreated = 0;

        // Pharmacy 1 → Supplier 1: cash terms
        BuyerSupplierRelationship::updateOrCreate(
            ['buyer_company_id' => $pharmacy1->id, 'supplier_company_id' => $supplier1->id],
            [
                'payment_terms' => PaymentTerms::CASH,
                'credit_limit' => null,
                'current_balance' => 0,
                'is_approved' => true,
                'approved_at' => now(),
                'notes' => 'عميل نقدي - دفع فوري',
            ]
        );
        $relationshipsCreated++;

        // Pharmacy 1 → Supplier 2 (hybrid): net 30 with credit
        BuyerSupplierRelationship::updateOrCreate(
            ['buyer_company_id' => $pharmacy1->id, 'supplier_company_id' => $supplier2->id],
            [
                'payment_terms' => PaymentTerms::NET_30,
                'credit_limit' => 50000.00,
                'current_balance' => 5000.00,
                'is_approved' => true,
                'approved_at' => now()->subMonths(3),
                'notes' => 'عميل مميز - آجل 30 يوم',
            ]
        );
        $relationshipsCreated++;

        // Pharmacy 2 → Supplier 2: net 15
        BuyerSupplierRelationship::updateOrCreate(
            ['buyer_company_id' => $pharmacy2->id, 'supplier_company_id' => $supplier2->id],
            [
                'payment_terms' => PaymentTerms::NET_15,
                'credit_limit' => 25000.00,
                'current_balance' => 0,
                'is_approved' => true,
                'approved_at' => now()->subMonths(1),
                'notes' => 'عميل جديد - آجل 15 يوم',
            ]
        );
        $relationshipsCreated++;

        // Salesman company → Supplier 3 (wholesale): net 60 with high credit
        if ($salesmanCompany) {
            BuyerSupplierRelationship::updateOrCreate(
                ['buyer_company_id' => $salesmanCompany->id, 'supplier_company_id' => $supplier3->id],
                [
                    'payment_terms' => PaymentTerms::NET_60,
                    'credit_limit' => 200000.00,
                    'current_balance' => 15000.00,
                    'is_approved' => true,
                    'approved_at' => now()->subMonths(6),
                    'notes' => 'مندوب مبيعات مستقل - عميل جملة - آجل 60 يوم',
                ]
            );
            $relationshipsCreated++;

            // Salesman company → Supplier 2 (hybrid): net 30
            BuyerSupplierRelationship::updateOrCreate(
                ['buyer_company_id' => $salesmanCompany->id, 'supplier_company_id' => $supplier2->id],
                [
                    'payment_terms' => PaymentTerms::NET_30,
                    'credit_limit' => 100000.00,
                    'current_balance' => 0,
                    'is_approved' => true,
                    'approved_at' => now()->subMonths(2),
                    'notes' => 'مندوب مبيعات مستقل - آجل 30 يوم',
                ]
            );
            $relationshipsCreated++;
        }

        $this->command->info("تم إنشاء {$relationshipsCreated} علاقة مورد-مشتري.");

        // Seed custom price lists
        $priceListsCreated = 0;

        // Pharmacy 1 gets a special price on Aspirin from Supplier 2
        $aspirin = Product::where('sku', 'ASP-100-001')->first();
        if ($aspirin && $pharmacy1 && $supplier2) {
            PriceList::updateOrCreate(
                ['supplier_company_id' => $supplier2->id, 'buyer_company_id' => $pharmacy1->id, 'product_id' => $aspirin->id],
                [
                    'custom_price' => 10.50,
                    'min_qty' => 5,
                    'valid_from' => now()->subMonth(),
                    'valid_to' => now()->addMonths(6),
                    'is_active' => true,
                    'notes' => 'سعر خاص للعميل المميز - أسبرين',
                ]
            );
            $priceListsCreated++;
        }

        // Salesman company gets special wholesale on Vitamin D3 from Supplier 3
        $vitD3 = Product::where('sku', 'VIT-D3-001')->first();
        if ($vitD3 && $salesmanCompany && $supplier3) {
            PriceList::updateOrCreate(
                ['supplier_company_id' => $supplier3->id, 'buyer_company_id' => $salesmanCompany->id, 'product_id' => $vitD3->id],
                [
                    'custom_price' => 38.00,
                    'min_qty' => 50,
                    'valid_from' => now()->subWeeks(2),
                    'valid_to' => null,
                    'is_active' => true,
                    'notes' => 'سعر خاص لمندوب الجملة - فيتامين د3',
                ]
            );
            $priceListsCreated++;
        }

        $this->command->info("تم إنشاء {$priceListsCreated} سعر مخصص.");

        // Seed salesman-pharmacy client assignments
        $clientsCreated = 0;

        // Supplier-associated salesman (from supplier 2) serves pharmacy 1 and 2
        $supplierSalesman = User::where('email', 'salesman1@pharmadist.com')->first();
        if ($supplierSalesman) {
            SalesmanClient::updateOrCreate(
                ['salesman_user_id' => $supplierSalesman->id, 'pharmacy_company_id' => $pharmacy1->id],
                ['is_active' => true, 'assigned_at' => now()->subMonths(2)]
            );
            $clientsCreated++;

            SalesmanClient::updateOrCreate(
                ['salesman_user_id' => $supplierSalesman->id, 'pharmacy_company_id' => $pharmacy2->id],
                ['is_active' => true, 'assigned_at' => now()->subMonth()]
            );
            $clientsCreated++;
        }

        // Independent salesman company serves pharmacy 2 and 3
        $pharmacy3 = $pharmacies->skip(2)->first();
        if ($salesmanCompany) {
            SalesmanClient::updateOrCreate(
                ['salesman_company_id' => $salesmanCompany->id, 'pharmacy_company_id' => $pharmacy2->id],
                ['salesman_user_id' => null, 'is_active' => true, 'assigned_at' => now()->subMonths(4)]
            );
            $clientsCreated++;

            if ($pharmacy3) {
                SalesmanClient::updateOrCreate(
                    ['salesman_company_id' => $salesmanCompany->id, 'pharmacy_company_id' => $pharmacy3->id],
                    ['salesman_user_id' => null, 'is_active' => true, 'assigned_at' => now()->subMonths(3)]
                );
                $clientsCreated++;
            }
        }

        $this->command->info("تم إنشاء {$clientsCreated} تعيين مندوب-صيدلية.");
    }
}
