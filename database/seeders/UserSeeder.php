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

        // Create Supplier Users (Ahmed → company 4, Fatima → company 2)
        if ($suppliers->isNotEmpty()) {
            User::updateOrCreate(
                ['email' => 'ahmed@medsupply.com'],
                [
                    'name' => 'أحمد محمد العلي',
                    'password' => Hash::make('password123'),
                    'company_id' => 4,
                    'role' => UserRole::SUPPLIER,
                ]
            );

            User::updateOrCreate(
                ['email' => 'fatima@pharmadist.com'],
                [
                    'name' => 'فاطمة علي السالم',
                    'password' => Hash::make('password123'),
                    'company_id' => 1,
                    'role' => UserRole::SUPPLIER,
                ]
            );

            $supplier3 = $suppliers->skip(2)->first();
            if ($supplier3) {
                User::updateOrCreate(
                    ['email' => 'omar@globalmed.com'],
                    [
                        'name' => 'عمر خالد الغامدي',
                        'password' => Hash::make('password123'),
                        'company_id' => $supplier3->id,
                        'role' => UserRole::SUPPLIER,
                    ]
                );
            }

            $supplier4 = $suppliers->skip(3)->first();
            if ($supplier4) {
                User::updateOrCreate(
                    ['email' => 'nora@healthworld.com'],
                    [
                        'name' => 'نورة عبدالرحمن السعيد',
                        'password' => Hash::make('password123'),
                        'company_id' => $supplier4->id,
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
        // Ensure drivers are attached to supplier companies with id 2 and 4
        if ($suppliers->isNotEmpty()) {
            $supplier1 = $suppliers->first();
            User::updateOrCreate(
                ['email' => 'driver1@medsupply.com'],
                [
                    'name' => 'عبدالرحمن صالح الدوسري',
                    'phone' => '+966501234567',
                    'password' => Hash::make('password123'),
                    'company_id' => $supplier1->id,
                    'role' => UserRole::DRIVER,
                ]
            );

            $supplier2 = $suppliers->firstWhere('id', 2) ?? $suppliers->skip(1)->first();
            if ($supplier2) {
                User::updateOrCreate(
                    ['email' => 'driver2@pharmadist.com'],
                    [
                        'name' => 'سعد محمد القحطاني',
                        'phone' => '+966502345678',
                        'password' => Hash::make('password123'),
                        'company_id' => $supplier2->id,
                        'role' => UserRole::DRIVER,
                    ]
                );
            }

            $supplier3 = $suppliers->skip(2)->first();
            if ($supplier3) {
                User::updateOrCreate(
                    ['email' => 'driver3@healthsupply.com'],
                    [
                        'name' => 'يوسف أحمد العتيبي',
                        'phone' => '+966503456789',
                        'password' => Hash::make('password123'),
                        'company_id' => $supplier3->id,
                        'role' => UserRole::DRIVER,
                    ]
                );
            }

            $supplier4 = $suppliers->firstWhere('id', 4) ?? $suppliers->skip(3)->first();
            if ($supplier4) {
                User::updateOrCreate(
                    ['email' => 'driver4@healthworld.com'],
                    [
                        'name' => 'محمد سعيد الشمري',
                        'phone' => '+966504567890',
                        'password' => Hash::make('password123'),
                        'company_id' => $supplier4->id,
                        'role' => UserRole::DRIVER,
                    ]
                );
            }
        }

        $this->command->info('تم إنشاء المستخدمين بنجاح.');
    }
}
