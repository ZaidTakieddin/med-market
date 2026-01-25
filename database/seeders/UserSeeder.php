<?php

namespace Database\Seeders;

use App\Constants\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get companies
        $suppliers = Company::where('type', 'supplier')->get();
        $buyers = Company::where('type', 'buyer')->get();

        // Create Admin User
        User::updateOrCreate(
            ['email' => 'admin@medmarket.com'],
            [
                'name' => 'مدير النظام',
                'password' => Hash::make('password123'),
                'company_id' => null,
                'role' => UserRole::ADMIN,
            ]
        );

        // Create Supplier Users
        if ($suppliers->isNotEmpty()) {
            $supplier1 = $suppliers->first();
            User::updateOrCreate(
                ['email' => 'ahmed@medsupply.com'],
                [
                    'name' => 'أحمد محمد العلي',
                    'password' => Hash::make('password123'),
                    'company_id' => $supplier1->id,
                    'role' => UserRole::SUPPLIER,
                ]
            );

            if ($suppliers->count() > 1) {
                $supplier2 = $suppliers->skip(1)->first();
                User::updateOrCreate(
                    ['email' => 'fatima@pharmadist.com'],
                    [
                        'name' => 'فاطمة علي السالم',
                        'password' => Hash::make('password123'),
                        'company_id' => $supplier2->id,
                        'role' => UserRole::SUPPLIER,
                    ]
                );
            }
        }

        // Create Buyer Users (Pharmacists)
        if ($buyers->isNotEmpty()) {
            $buyer1 = $buyers->first();
            User::updateOrCreate(
                ['email' => 'khaled@citypharmacy.com'],
                [
                    'name' => 'خالد عبدالله النجار',
                    'password' => Hash::make('password123'),
                    'company_id' => $buyer1->id,
                    'role' => UserRole::BUYER,
                ]
            );

            if ($buyers->count() > 1) {
                $buyer2 = $buyers->skip(1)->first();
                User::updateOrCreate(
                    ['email' => 'sara@healthcarepharm.com'],
                    [
                        'name' => 'سارة حسن المالكي',
                        'password' => Hash::make('password123'),
                        'company_id' => $buyer2->id,
                        'role' => UserRole::BUYER,
                    ]
                );
            }

            if ($buyers->count() > 2) {
                $buyer3 = $buyers->skip(2)->first();
                User::updateOrCreate(
                    ['email' => 'mohammed@communitypharm.com'],
                    [
                        'name' => 'محمد إبراهيم الحربي',
                        'password' => Hash::make('password123'),
                        'company_id' => $buyer3->id,
                        'role' => UserRole::BUYER,
                    ]
                );
            }
        }

        // Create Driver Users (belonging to suppliers)
        if ($suppliers->isNotEmpty()) {
            $supplier1 = $suppliers->first();
            User::updateOrCreate(
                ['email' => 'driver1@medsupply.com'],
                [
                    'name' => 'عبدالرحمن صالح الدوسري',
                    'password' => Hash::make('password123'),
                    'company_id' => $supplier1->id,
                    'role' => UserRole::DRIVER,
                ]
            );

            // Add more drivers if multiple suppliers exist
            if ($suppliers->count() > 1) {
                $supplier2 = $suppliers->skip(1)->first();
                User::updateOrCreate(
                    ['email' => 'driver2@pharmadist.com'],
                    [
                        'name' => 'سعد محمد القحطاني',
                        'password' => Hash::make('password123'),
                        'company_id' => $supplier2->id,
                        'role' => UserRole::DRIVER,
                    ]
                );
            }

            if ($suppliers->count() > 2) {
                $supplier3 = $suppliers->skip(2)->first();
                User::updateOrCreate(
                    ['email' => 'driver3@healthsupply.com'],
                    [
                        'name' => 'يوسف أحمد العتيبي',
                        'password' => Hash::make('password123'),
                        'company_id' => $supplier3->id,
                        'role' => UserRole::DRIVER,
                    ]
                );
            }
        }

        $this->command->info('تم إنشاء المستخدمين بنجاح.');
    }
}
