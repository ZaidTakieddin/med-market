<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get cities
        $damascus = City::where('slug', 'damascus')->first();
        $aleppo = City::where('slug', 'aleppo')->first();
        $homs = City::where('slug', 'homs')->first();
        $latakia = City::where('slug', 'latakia')->first();
        $tartus = City::where('slug', 'tartus')->first();
        $hama = City::where('slug', 'hama')->first();
        $deirEzZor = City::where('slug', 'deir-ez-zor')->first();
        $sweida = City::where('slug', 'sweida')->first();

        $regions = [];

        // Damascus regions
        if ($damascus) {
            $regions = array_merge($regions, [
                [
                    'city_id' => $damascus->id,
                    'name' => 'المزة',
                    'slug' => 'mazzeh',
                    'description' => 'منطقة المزة في دمشق',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
                [
                    'city_id' => $damascus->id,
                    'name' => 'المالكي',
                    'slug' => 'malki',
                    'description' => 'منطقة المالكي في دمشق',
                    'sort_order' => 2,
                    'is_active' => true,
                ],
                [
                    'city_id' => $damascus->id,
                    'name' => 'الروضة',
                    'slug' => 'rawda',
                    'description' => 'منطقة الروضة في دمشق',
                    'sort_order' => 3,
                    'is_active' => true,
                ],
                [
                    'city_id' => $damascus->id,
                    'name' => 'الشام',
                    'slug' => 'sham',
                    'description' => 'منطقة الشام القديمة',
                    'sort_order' => 4,
                    'is_active' => true,
                ],
                [
                    'city_id' => $damascus->id,
                    'name' => 'كفرسوسة',
                    'slug' => 'kafr-sousa',
                    'description' => 'منطقة كفرسوسة',
                    'sort_order' => 5,
                    'is_active' => true,
                ],
                [
                    'city_id' => $damascus->id,
                    'name' => 'الميدان',
                    'slug' => 'meidan',
                    'description' => 'منطقة الميدان',
                    'sort_order' => 6,
                    'is_active' => true,
                ],
                [
                    'city_id' => $damascus->id,
                    'name' => 'الزاهرة',
                    'slug' => 'zahira',
                    'description' => 'منطقة الزاهرة',
                    'sort_order' => 7,
                    'is_active' => true,
                ],
            ]);
        }

        // Aleppo regions
        if ($aleppo) {
            $regions = array_merge($regions, [
                [
                    'city_id' => $aleppo->id,
                    'name' => 'الجميلية',
                    'slug' => 'jamiliya',
                    'description' => 'منطقة الجميلية في حلب',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
                [
                    'city_id' => $aleppo->id,
                    'name' => 'السليمانية',
                    'slug' => 'sulaymaniya',
                    'description' => 'منطقة السليمانية في حلب',
                    'sort_order' => 2,
                    'is_active' => true,
                ],
                [
                    'city_id' => $aleppo->id,
                    'name' => 'العزيزية',
                    'slug' => 'aziziya',
                    'description' => 'منطقة العزيزية في حلب',
                    'sort_order' => 3,
                    'is_active' => true,
                ],
                [
                    'city_id' => $aleppo->id,
                    'name' => 'الشهباء',
                    'slug' => 'shahba',
                    'description' => 'منطقة الشهباء في حلب',
                    'sort_order' => 4,
                    'is_active' => true,
                ],
            ]);
        }

        // Homs regions
        if ($homs) {
            $regions = array_merge($regions, [
                [
                    'city_id' => $homs->id,
                    'name' => 'المركز',
                    'slug' => 'center',
                    'description' => 'وسط مدينة حمص',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
                [
                    'city_id' => $homs->id,
                    'name' => 'الكرامة',
                    'slug' => 'karama',
                    'description' => 'منطقة الكرامة في حمص',
                    'sort_order' => 2,
                    'is_active' => true,
                ],
                [
                    'city_id' => $homs->id,
                    'name' => 'الوعر',
                    'slug' => 'waer',
                    'description' => 'منطقة الوعر في حمص',
                    'sort_order' => 3,
                    'is_active' => true,
                ],
            ]);
        }

        // Latakia regions
        if ($latakia) {
            $regions = array_merge($regions, [
                [
                    'city_id' => $latakia->id,
                    'name' => 'المركز',
                    'slug' => 'center',
                    'description' => 'وسط مدينة اللاذقية',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
                [
                    'city_id' => $latakia->id,
                    'name' => 'الشاطئ الأزرق',
                    'slug' => 'blue-beach',
                    'description' => 'منطقة الشاطئ الأزرق',
                    'sort_order' => 2,
                    'is_active' => true,
                ],
            ]);
        }

        // Tartus regions
        if ($tartus) {
            $regions = array_merge($regions, [
                [
                    'city_id' => $tartus->id,
                    'name' => 'المركز',
                    'slug' => 'center',
                    'description' => 'وسط مدينة طرطوس',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
            ]);
        }

        // Hama regions
        if ($hama) {
            $regions = array_merge($regions, [
                [
                    'city_id' => $hama->id,
                    'name' => 'المركز',
                    'slug' => 'center',
                    'description' => 'وسط مدينة حماة',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
            ]);
        }

        // Deir ez-Zor regions
        if ($deirEzZor) {
            $regions = array_merge($regions, [
                [
                    'city_id' => $deirEzZor->id,
                    'name' => 'المركز',
                    'slug' => 'center',
                    'description' => 'وسط مدينة دير الزور',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
            ]);
        }

        // Sweida regions
        if ($sweida) {
            $regions = array_merge($regions, [
                [
                    'city_id' => $sweida->id,
                    'name' => 'المركز',
                    'slug' => 'center',
                    'description' => 'وسط مدينة السويداء',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
            ]);
        }

        foreach ($regions as $regionData) {
            Region::updateOrCreate(
                [
                    'city_id' => $regionData['city_id'],
                    'name' => $regionData['name'],
                ],
                $regionData
            );
        }

        $this->command->info('تم تحديث/إنشاء '.count($regions).' منطقة بنجاح.');
    }
}
