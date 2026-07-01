<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * Determine if the user can view any companies.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view companies
    }

    /**
     * Determine if the user can view the company.
     */
    public function view(User $user, Company $company): bool
    {
        return true; // All authenticated users can view companies
    }

    /**
     * Determine if the user can create companies.
     */
    public function create(User $user): bool
    {
        // Only admin can create companies
        return $user->isAdmin();
    }

    /**
     * Determine if the user can update the company.
     */
    public function update(User $user, Company $company): bool
    {
        // Only admin can update companies
        return $user->isAdmin();
    }

    /**
     * Determine if the user can delete the company.
     */
    public function delete(User $user, Company $company): bool
    {
        // Only admin can delete companies
        return $user->isAdmin();
    }

    /**
     * Determine if the user can manage (upload/remove) the company's logo.
     * Admins can manage any company; other users only their own company.
     */
    public function manageLogo(User $user, Company $company): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->company_id === $company->id;
    }
}
