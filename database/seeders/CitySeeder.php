<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cities = [
            [
                'name' => 'دمشق',
                'slug' => 'damascus',
                'description' => 'عاصمة سوريا وأكبر مدنها',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'حلب',
                'slug' => 'aleppo',
                'description' => 'ثاني أكبر مدينة في سوريا',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'حمص',
                'slug' => 'homs',
                'description' => 'ثالث أكبر مدينة في سوريا',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'اللاذقية',
                'slug' => 'latakia',
                'description' => 'مدينة ساحلية على البحر المتوسط',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'طرطوس',
                'slug' => 'tartus',
                'description' => 'مدينة ساحلية على البحر المتوسط',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'حماة',
                'slug' => 'hama',
                'description' => 'مدينة على نهر العاصي',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'name' => 'دير الزور',
                'slug' => 'deir-ez-zor',
                'description' => 'مدينة شرق سوريا على نهر الفرات',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'السويداء',
                'slug' => 'sweida',
                'description' => 'مدينة في جنوب سوريا',
                'sort_order' => 8,
                'is_active' => true,
            ],
        ];

        foreach ($cities as $cityData) {
            City::updateOrCreate(
                ['name' => $cityData['name']],
                $cityData
            );
        }

        $this->command->info('تم تحديث/إنشاء '.count($cities).' مدينة بنجاح.');
    }
}
