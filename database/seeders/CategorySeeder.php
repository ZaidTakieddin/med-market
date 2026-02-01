<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'مسكنات', 'description' => 'أدوية مسكنة للآلام', 'sort_order' => 1],
            ['name' => 'مضادات حيوية', 'description' => 'مضادات حيوية لعلاج الالتهابات', 'sort_order' => 2],
            ['name' => 'مضادات التهاب', 'description' => 'أدوية مضادة للالتهابات', 'sort_order' => 3],
            ['name' => 'معدة', 'description' => 'أدوية لعلاج مشاكل المعدة', 'sort_order' => 4],
            ['name' => 'مضادات حساسية', 'description' => 'أدوية لعلاج الحساسية', 'sort_order' => 5],
            ['name' => 'قلب وأوعية', 'description' => 'أدوية لعلاج أمراض القلب والأوعية الدموية', 'sort_order' => 6],
            ['name' => 'سكري', 'description' => 'أدوية لعلاج مرض السكري', 'sort_order' => 7],
            ['name' => 'فيتامينات', 'description' => 'مكملات فيتامينية', 'sort_order' => 8],
            ['name' => 'مكملات غذائية', 'description' => 'مكملات غذائية متنوعة', 'sort_order' => 9],
        ];

        foreach ($categories as $categoryData) {
            Category::updateOrCreate(
                ['name' => $categoryData['name']],
                $categoryData
            );
        }

        $this->command->info('تم تحديث/إنشاء ' . count($categories) . ' فئة بنجاح.');
    }
}
