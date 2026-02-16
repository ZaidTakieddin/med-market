<?php

namespace Database\Seeders;

use App\Constants\CompanyType;
use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Suppliers
        $suppliers = [
            [
                'name' => 'شركة الإمدادات الطبية',
                'description' => 'مورّد رائد للمستلزمات الطبية والأدوية في المنطقة. نقدم تشكيلة واسعة من المنتجات الطبية عالية الجودة مع خدمة توصيل سريعة وموثوقة.',
                'type' => CompanyType::SUPPLIER,
                'email' => 'contact@medsupply.com',
                'phone' => '+966-11-123-4567',
                'address' => 'الرياض، حي العليا، شارع الملك فهد، مبنى رقم 123',
                'tax_id' => 'TAX-SUP-001',
                'registration_number' => 'REG-SUP-2024-001',
                'is_active' => true,
            ],
            [
                'name' => 'شركة توزيع الأدوية المحدودة',
                'description' => 'متخصصون في توزيع الأدوية والمستحضرات الصيدلانية للمستشفيات والصيدليات. شراكات مع كبرى الشركات العالمية.',
                'type' => CompanyType::SUPPLIER,
                'email' => 'info@pharmadist.com',
                'phone' => '+966-11-234-5678',
                'address' => 'جدة، حي الزهراء، طريق الملك عبدالعزيز، مبنى رقم 456',
                'tax_id' => 'TAX-SUP-002',
                'registration_number' => 'REG-SUP-2024-002',
                'is_active' => true,
            ],
            [
                'name' => 'الإمدادات الطبية العالمية',
                'description' => 'شريكك الموثوق للإمدادات الطبية العالمية. نوفر منتجات من أفضل المصانع العالمية مع ضمان الجودة وأفضل الأسعار.',
                'type' => CompanyType::SUPPLIER,
                'email' => 'sales@globalmed.com',
                'phone' => '+966-11-345-6789',
                'address' => 'الدمام، حي الفيصلية، شارع الأمير سلطان، مبنى رقم 789',
                'tax_id' => 'TAX-SUP-003',
                'registration_number' => 'REG-SUP-2024-003',
                'is_active' => true,
            ],
        ];

        // Create Buyers (Pharmacies)
        $buyers = [
            [
                'name' => 'صيدلية المدينة',
                'description' => 'صيدلية مجتمعية تخدم سكان حي النرجس والمناطق المجاورة. نقدم أدوية ومستلزمات طبية بأسعار تنافسية وخدمة عملاء متميزة.',
                'type' => CompanyType::BUYER,
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
                'email' => 'info@communitypharm.com',
                'phone' => '+966-11-678-9012',
                'address' => 'الخبر، حي الكورنيش، شارع الكورنيش، مبنى رقم 300',
                'tax_id' => 'TAX-BUY-003',
                'registration_number' => 'REG-BUY-2024-003',
                'is_active' => true,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Company::create($supplier);
        }

        foreach ($buyers as $buyer) {
            Company::create($buyer);
        }

        $this->command->info('تم إنشاء ' . count($suppliers) . ' مورد و ' . count($buyers) . ' صيدلية.');
    }
}
