<?php

namespace Database\Seeders;

use App\Models\Manufacturer;
use Illuminate\Database\Seeder;

class ManufacturerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $manufacturers = [
            [
                'name' => 'شركة الأدوية الوطنية',
                'description' => 'شركة رائدة في صناعة الأدوية',
                'country' => 'السعودية',
                'sort_order' => 1,
            ],
            [
                'name' => 'شركة الأدوية المتقدمة',
                'description' => 'شركة متخصصة في الأدوية المتقدمة',
                'country' => 'السعودية',
                'sort_order' => 2,
            ],
            [
                'name' => 'شركة المكملات الصحية',
                'description' => 'شركة متخصصة في المكملات الغذائية',
                'country' => 'السعودية',
                'sort_order' => 3,
            ],
            [
                'name' => 'شركة الصحة العالمية',
                'description' => 'شركة عالمية في صناعة الأدوية',
                'country' => 'الإمارات',
                'sort_order' => 4,
            ],
            [
                'name' => 'شركة الدواء الحديث',
                'description' => 'شركة حديثة في صناعة الأدوية',
                'country' => 'مصر',
                'sort_order' => 5,
            ],
        ];

        foreach ($manufacturers as $manufacturerData) {
            Manufacturer::updateOrCreate(
                ['name' => $manufacturerData['name']],
                $manufacturerData
            );
        }

        $this->command->info('تم تحديث/إنشاء ' . count($manufacturers) . ' شركة مصنعة بنجاح.');
    }
}
