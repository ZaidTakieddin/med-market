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
        User::create([
            'name' => 'مدير النظام',
            'email' => 'admin@medmarket.com',
            'password' => Hash::make('password123'),
            'company_id' => null,
            'role' => UserRole::ADMIN,
        ]);

        // Create Supplier Users
        if ($suppliers->isNotEmpty()) {
            $supplier1 = $suppliers->first();
            User::create([
                'name' => 'أحمد محمد العلي',
                'email' => 'ahmed@medsupply.com',
                'password' => Hash::make('password123'),
                'company_id' => $supplier1->id,
                'role' => UserRole::SUPPLIER,
            ]);

            if ($suppliers->count() > 1) {
                $supplier2 = $suppliers->skip(1)->first();
                User::create([
                    'name' => 'فاطمة علي السالم',
                    'email' => 'fatima@pharmadist.com',
                    'password' => Hash::make('password123'),
                    'company_id' => $supplier2->id,
                    'role' => UserRole::SUPPLIER,
                ]);
            }
        }

        // Create Buyer Users (Pharmacists)
        if ($buyers->isNotEmpty()) {
            $buyer1 = $buyers->first();
            User::create([
                'name' => 'خالد عبدالله النجار',
                'email' => 'khaled@citypharmacy.com',
                'password' => Hash::make('password123'),
                'company_id' => $buyer1->id,
                'role' => UserRole::BUYER,
            ]);

            if ($buyers->count() > 1) {
                $buyer2 = $buyers->skip(1)->first();
                User::create([
                    'name' => 'سارة حسن المالكي',
                    'email' => 'sara@healthcarepharm.com',
                    'password' => Hash::make('password123'),
                    'company_id' => $buyer2->id,
                    'role' => UserRole::BUYER,
                ]);
            }

            if ($buyers->count() > 2) {
                $buyer3 = $buyers->skip(2)->first();
                User::create([
                    'name' => 'محمد إبراهيم الحربي',
                    'email' => 'mohammed@communitypharm.com',
                    'password' => Hash::make('password123'),
                    'company_id' => $buyer3->id,
                    'role' => UserRole::BUYER,
                ]);
            }
        }

        // Create Driver Users (belonging to suppliers)
        if ($suppliers->isNotEmpty()) {
            $supplier1 = $suppliers->first();
            User::create([
                'name' => 'عبدالرحمن صالح الدوسري',
                'email' => 'driver1@medsupply.com',
                'password' => Hash::make('password123'),
                'company_id' => $supplier1->id,
                'role' => UserRole::DRIVER,
            ]);
        }

        $this->command->info('تم إنشاء المستخدمين بنجاح.');
    }
}
