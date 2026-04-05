<?php

namespace Database\Seeders;

use App\Constants\BuyerClassification;
use App\Constants\CompanyType;
use App\Constants\SupplierClassification;
use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'شركة الإمدادات الطبية',
                'description' => 'مورّد رائد للمستلزمات الطبية والأدوية في المنطقة. نقدم تشكيلة واسعة من المنتجات الطبية عالية الجودة مع خدمة توصيل سريعة وموثوقة.',
                'type' => CompanyType::SUPPLIER,
                'supplier_classification' => SupplierClassification::RETAIL,
                'email' => 'contact@medsupply.com',
                'phone' => '+966-11-123-4567',
                'address' => 'الرياض، حي العليا، شارع الملك فهد، مبنى رقم 123',
                'tax_id' => 'TAX-SUP-001',
                'registration_number' => 'REG-SUP-2024-001',
                'is_active' => true,
            ],
            [
                'name' => 'شركة توزيع الأدوية المحدودة',
                'description' => 'متخصصون في توزيع الأدوية والمستحضرات الصيدلانية بالجملة والمفرق. شراكات مع كبرى الشركات العالمية.',
                'type' => CompanyType::SUPPLIER,
                'supplier_classification' => SupplierClassification::HYBRID,
                'min_order_amount' => 500.00,
                'email' => 'info@pharmadist.com',
                'phone' => '+966-11-234-5678',
                'address' => 'جدة، حي الزهراء، طريق الملك عبدالعزيز، مبنى رقم 456',
                'tax_id' => 'TAX-SUP-002',
                'registration_number' => 'REG-SUP-2024-002',
                'is_active' => true,
            ],
            [
                'name' => 'الإمدادات الطبية العالمية',
                'description' => 'شريكك الموثوق للإمدادات الطبية بالجملة. نوفر منتجات من أفضل المصانع العالمية بكميات كبيرة وأسعار تنافسية.',
                'type' => CompanyType::SUPPLIER,
                'supplier_classification' => SupplierClassification::WHOLESALE,
                'min_order_amount' => 1000.00,
                'email' => 'sales@globalmed.com',
                'phone' => '+966-11-345-6789',
                'address' => 'الدمام، حي الفيصلية، شارع الأمير سلطان، مبنى رقم 789',
                'tax_id' => 'TAX-SUP-003',
                'registration_number' => 'REG-SUP-2024-003',
                'is_active' => true,
            ],
            [
                'name' => 'شركة الصحة العالمية للتوزيع',
                'description' => 'مورّد متخصص في الأدوية والمستحضرات الطبية. نقدم تشكيلة متنوعة من المنتجات بجودة عالية للصيدليات.',
                'type' => CompanyType::SUPPLIER,
                'supplier_classification' => SupplierClassification::RETAIL,
                'email' => 'orders@healthworld.com',
                'phone' => '+966-11-789-0123',
                'address' => 'مكة المكرمة، حي العزيزية، شارع إبراهيم الخليل',
                'tax_id' => 'TAX-SUP-004',
                'registration_number' => 'REG-SUP-2024-004',
                'is_active' => true,
            ],
        ];

        $buyers = [
            [
                'name' => 'صيدلية المدينة',
                'description' => 'صيدلية مجتمعية تخدم سكان حي النرجس والمناطق المجاورة. نقدم أدوية ومستلزمات طبية بأسعار تنافسية وخدمة عملاء متميزة.',
                'type' => CompanyType::BUYER,
                'buyer_classification' => BuyerClassification::PHARMACY,
                'email' => 'orders@citypharmacy.com',
                'phone' => '+966-11-456-7890',
                'address' => 'الرياض، حي النرجس، شارع التحلية، مبنى رقم 100',
                'tax_id' => 'TAX-BUY-001',
                'registration_number' => 'REG-BUY-2024-001',
                'is_active' => true,
            ],
            [
                'name' => 'صيدلية الرعاية الصحية',
                'description' => 'صيدلية متكاملة متخصصة في الرعاية الصحية. نوفر تشكيلة واسعة من الأدوية والمكملات الغذائية مع استشارات صيدلانية مجانية.',
                'type' => CompanyType::BUYER,
                'buyer_classification' => BuyerClassification::PHARMACY,
                'email' => 'purchasing@healthcarepharm.com',
                'phone' => '+966-11-567-8901',
                'address' => 'جدة، حي الروابي، طريق الملك عبدالله، مبنى رقم 200',
                'tax_id' => 'TAX-BUY-002',
                'registration_number' => 'REG-BUY-2024-002',
                'is_active' => true,
            ],
            [
                'name' => 'صيدلية المجتمع',
                'description' => 'صيدلية عائلية نخدم المجتمع منذ سنوات. نلتزم بتوفير منتجات أصلية وجودة عالية مع أسعار مناسبة لجميع الفئات.',
                'type' => CompanyType::BUYER,
                'buyer_classification' => BuyerClassification::PHARMACY,
                'email' => 'info@communitypharm.com',
                'phone' => '+966-11-678-9012',
                'address' => 'الخبر، حي الكورنيش، شارع الكورنيش، مبنى رقم 300',
                'tax_id' => 'TAX-BUY-003',
                'registration_number' => 'REG-BUY-2024-003',
                'is_active' => true,
            ],
            [
                'name' => 'مؤسسة الأمل للتوزيع الطبي',
                'description' => 'مؤسسة مندوب مبيعات مستقل متخصصة في توزيع الأدوية للصيدليات في المنطقة الشرقية. نشتري بالجملة ونوزع بأسعار تنافسية.',
                'type' => CompanyType::BUYER,
                'buyer_classification' => BuyerClassification::SALESMAN,
                'email' => 'info@alamaldist.com',
                'phone' => '+966-11-890-1234',
                'address' => 'الدمام، حي الشاطئ، شارع الخليج، مبنى رقم 55',
                'tax_id' => 'TAX-BUY-004',
                'registration_number' => 'REG-BUY-2024-004',
                'is_active' => true,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Company::updateOrCreate(
                ['email' => $supplier['email']],
                $supplier
            );
        }

        foreach ($buyers as $buyer) {
            Company::updateOrCreate(
                ['email' => $buyer['email']],
                $buyer
            );
        }

        $this->command->info('تم إنشاء ' . count($suppliers) . ' مورد و ' . count($buyers) . ' مشتري (صيدليات + مندوبين).');
    }
}
