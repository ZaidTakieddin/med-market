<?php

namespace App\Domain\Users;

use App\Constants\BuyerClassification;
use App\Constants\CompanyType;
use App\Constants\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegistrationService
{
    /**
     * Register a new buyer (pharmacy) together with its own company.
     *
     * Security: the role and the company are assigned by the server, never taken
     * from client input. Public registration can ONLY create a buyer that owns a
     * brand-new pharmacy company — privileged roles (supplier/salesman/driver/admin)
     * and joining an existing company are impossible through this path. See codebase
     * rules §17 lesson #1 (privilege escalation).
     *
     * @param  array<string, mixed>  $data  Validated RegisterRequest data
     */
    public function registerBuyer(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $company = Company::create([
                'name' => $data['company_name'],
                'type' => CompanyType::BUYER,
                'buyer_classification' => BuyerClassification::PHARMACY,
                'phone' => $data['company_phone'] ?? null,
                'address' => $data['company_address'] ?? null,
                'region_id' => $data['region_id'] ?? null,
                'is_active' => true,
            ]);

            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'company_id' => $company->id,
                'role' => UserRole::BUYER,
            ]);
        });
    }
}
